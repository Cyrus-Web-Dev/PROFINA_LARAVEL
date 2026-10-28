<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilesAndExportTest extends TestCase
{
    use RefreshDatabase;

    private function draftJournal()
    {
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pend = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        return app(JournalService::class)->create(
            ['journal_date' => '2026-03-01', 'type' => 'umum', 'description' => 'Tes'],
            [['account_id' => $kas->id, 'debit' => 10, 'credit' => 0], ['account_id' => $pend->id, 'debit' => 0, 'credit' => 10]]
        );
    }

    public function test_manager_can_create_a_backup_and_only_valid_filenames_can_be_downloaded(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.backup.store'))->assertSessionHasNoErrors();

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/mkp_backup_.*_data\.sql$/', $files[0]); // SQLite -> mode data (PHP)
        $this->assertStringContainsString('INSERT INTO', Storage::disk('local')->get($files[0]));

        $this->actingAs($admin)->get(route('admin.backup.download', basename($files[0])))->assertOk();
        $this->actingAs($admin)->get(route('admin.backup.download', 'evil.sql'))->assertNotFound();
    }

    public function test_attachments_can_be_uploaded_viewed_and_deleted_by_authorised_users(): void
    {
        Storage::fake('local');
        $admin = $this->makeUser('admin');
        $viewer = $this->makeUser('viewer');
        $journal = $this->draftJournal();

        $this->actingAs($viewer)->post(route('attachments.store'), [
            'type' => 'journal', 'id' => $journal->id, 'file' => UploadedFile::fake()->image('nota.jpg', 200, 200),
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('attachments.store'), [
            'type' => 'journal', 'id' => $journal->id, 'file' => UploadedFile::fake()->image('nota.jpg', 200, 200),
        ])->assertSessionHasNoErrors();

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($viewer)->get(route('attachments.show', $attachment))->assertOk(); // viewer boleh melihat

        $this->actingAs($admin)->post(route('attachments.store'), [
            'type' => 'journal', 'id' => $journal->id, 'file' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('file');

        $this->actingAs($admin)->delete(route('attachments.destroy', $attachment))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertSame(0, Attachment::count());
    }

    public function test_excel_export_of_the_trial_balance_downloads_a_spreadsheet(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('reports.trial-balance.excel', ['as_of_date' => '2026-12-31']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
