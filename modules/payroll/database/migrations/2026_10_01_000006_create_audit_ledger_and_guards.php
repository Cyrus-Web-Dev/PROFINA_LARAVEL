<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan integritas data (PostgreSQL):
 *  1. audit_ledger  : jejak audit rantai-hash, append-only (UPDATE/DELETE/TRUNCATE ditolak trigger).
 *  2. Kontrak       : isi kontrak yang sudah disetujui tidak bisa diubah/dihapus.
 *  3. Payroll       : putaran yang sudah dikunci (disetujui) & item-itemnya tidak bisa diubah nilainya/dihapus.
 *  4. Satu putaran reguler per bulan.
 *
 * Catatan operasional: agar pengaman ini tidak bisa dimatikan oleh aplikasi sendiri, jalankan aplikasi dengan
 * ROLE database yang BUKAN pemilik tabel (lihat docs/KEAMANAN.md > "Role database aplikasi").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_ledger', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('seq')->unique();
            $t->string('event', 80)->index();
            $t->unsignedBigInteger('actor_id')->nullable()->index();
            $t->string('actor_name', 150)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_label', 150)->nullable();
            $t->text('payload');                          // JSON kanonik persis seperti yang di-hash (TEXT, bukan jsonb, agar byte-nya tetap)
            $t->char('prev_hash', 64);
            $t->char('hash', 64)->unique();
            $t->string('occurred_at', 40);                // ISO-8601 UTC + mikrodetik, ikut di-hash
            $t->index(['subject_type', 'subject_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX uq_regular_run_per_month ON payroll_runs (period_year, period_month) WHERE type = 'regular' AND status NOT IN ('cancelled','rejected')");

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION pay_block_mutation() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'Tabel % bersifat append-only: data tidak boleh diubah atau dihapus.', TG_TABLE_NAME USING ERRCODE = 'restrict_violation';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_ledger_no_mutation BEFORE UPDATE OR DELETE ON audit_ledger
  FOR EACH ROW EXECUTE FUNCTION pay_block_mutation();
CREATE TRIGGER audit_ledger_no_truncate BEFORE TRUNCATE ON audit_ledger
  FOR EACH STATEMENT EXECUTE FUNCTION pay_block_mutation();

CREATE OR REPLACE FUNCTION pay_guard_contract() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN
    IF OLD.status NOT IN ('proposed','rejected','cancelled') THEN
      RAISE EXCEPTION 'Kontrak % berstatus % dan tidak boleh dihapus.', OLD.contract_number, OLD.status USING ERRCODE = 'restrict_violation';
    END IF;
    RETURN OLD;
  END IF;
  IF OLD.status IN ('approved','active','superseded','expired') THEN
    IF NEW.base_salary IS DISTINCT FROM OLD.base_salary
       OR NEW.employee_id IS DISTINCT FROM OLD.employee_id
       OR NEW.effective_date IS DISTINCT FROM OLD.effective_date
       OR NEW.terms_hash IS DISTINCT FROM OLD.terms_hash
       OR NEW.grade_id IS DISTINCT FROM OLD.grade_id
       OR NEW.version IS DISTINCT FROM OLD.version THEN
      RAISE EXCEPTION 'Isi kontrak % sudah disetujui dan terkunci. Buat versi kontrak baru.', OLD.contract_number USING ERRCODE = 'restrict_violation';
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER compensation_contracts_guard BEFORE UPDATE OR DELETE ON compensation_contracts
  FOR EACH ROW EXECUTE FUNCTION pay_guard_contract();

CREATE OR REPLACE FUNCTION pay_guard_contract_components() RETURNS trigger AS $$
DECLARE st text; cid bigint;
BEGIN
  IF TG_OP = 'INSERT' THEN cid := NEW.contract_id; ELSE cid := OLD.contract_id; END IF;
  SELECT status INTO st FROM compensation_contracts WHERE id = cid;
  IF st IN ('approved','active','superseded','expired') THEN
    RAISE EXCEPTION 'Komponen kontrak yang sudah disetujui tidak boleh diubah.' USING ERRCODE = 'restrict_violation';
  END IF;
  IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER contract_components_guard BEFORE INSERT OR UPDATE OR DELETE ON contract_components
  FOR EACH ROW EXECUTE FUNCTION pay_guard_contract_components();

CREATE OR REPLACE FUNCTION pay_guard_runs() RETURNS trigger AS $$
BEGIN
  IF TG_OP = 'DELETE' THEN
    IF OLD.locked_at IS NOT NULL THEN
      RAISE EXCEPTION 'Putaran payroll % sudah dikunci dan tidak boleh dihapus.', OLD.run_number USING ERRCODE = 'restrict_violation';
    END IF;
    RETURN OLD;
  END IF;
  IF OLD.locked_at IS NOT NULL AND (
        NEW.locked_at IS DISTINCT FROM OLD.locked_at
     OR NEW.snapshot_hash IS DISTINCT FROM OLD.snapshot_hash
     OR NEW.total_gross IS DISTINCT FROM OLD.total_gross
     OR NEW.total_net IS DISTINCT FROM OLD.total_net
     OR NEW.total_pph21 IS DISTINCT FROM OLD.total_pph21
     OR NEW.total_employer_bpjs IS DISTINCT FROM OLD.total_employer_bpjs
     OR NEW.total_employee_bpjs IS DISTINCT FROM OLD.total_employee_bpjs
     OR NEW.period_year IS DISTINCT FROM OLD.period_year
     OR NEW.period_month IS DISTINCT FROM OLD.period_month
     OR NEW.type IS DISTINCT FROM OLD.type) THEN
    RAISE EXCEPTION 'Nilai putaran payroll % sudah dikunci dan tidak boleh diubah.', OLD.run_number USING ERRCODE = 'restrict_violation';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER payroll_runs_guard BEFORE UPDATE OR DELETE ON payroll_runs
  FOR EACH ROW EXECUTE FUNCTION pay_guard_runs();

CREATE OR REPLACE FUNCTION pay_guard_items() RETURNS trigger AS $$
DECLARE locked timestamptz;
BEGIN
  SELECT locked_at INTO locked FROM payroll_runs WHERE id = OLD.payroll_run_id;
  IF locked IS NULL THEN
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
  END IF;
  IF TG_OP = 'DELETE' THEN
    RAISE EXCEPTION 'Item payroll pada putaran yang sudah dikunci tidak boleh dihapus.' USING ERRCODE = 'restrict_violation';
  END IF;
  IF NEW.employee_id IS DISTINCT FROM OLD.employee_id
     OR NEW.payroll_run_id IS DISTINCT FROM OLD.payroll_run_id
     OR NEW.gross_earnings IS DISTINCT FROM OLD.gross_earnings
     OR NEW.net_pay IS DISTINCT FROM OLD.net_pay
     OR NEW.pph21 IS DISTINCT FROM OLD.pph21
     OR NEW.item_hash IS DISTINCT FROM OLD.item_hash
     OR NEW.lines IS DISTINCT FROM OLD.lines
     OR NEW.bank_account_hash IS DISTINCT FROM OLD.bank_account_hash THEN
    RAISE EXCEPTION 'Nilai item payroll pada putaran yang sudah dikunci tidak boleh diubah.' USING ERRCODE = 'restrict_violation';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER payroll_items_guard BEFORE UPDATE OR DELETE ON payroll_items
  FOR EACH ROW EXECUTE FUNCTION pay_guard_items();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS payroll_items_guard ON payroll_items;
DROP TRIGGER IF EXISTS payroll_runs_guard ON payroll_runs;
DROP TRIGGER IF EXISTS contract_components_guard ON contract_components;
DROP TRIGGER IF EXISTS compensation_contracts_guard ON compensation_contracts;
DROP TRIGGER IF EXISTS audit_ledger_no_truncate ON audit_ledger;
DROP TRIGGER IF EXISTS audit_ledger_no_mutation ON audit_ledger;
DROP FUNCTION IF EXISTS pay_guard_items();
DROP FUNCTION IF EXISTS pay_guard_runs();
DROP FUNCTION IF EXISTS pay_guard_contract_components();
DROP FUNCTION IF EXISTS pay_guard_contract();
DROP FUNCTION IF EXISTS pay_block_mutation();
DROP INDEX IF EXISTS uq_regular_run_per_month;
SQL);
        }
        Schema::dropIfExists('audit_ledger');
    }
};
