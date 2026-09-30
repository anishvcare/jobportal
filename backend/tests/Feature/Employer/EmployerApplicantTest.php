<?php

use App\Enums\ApplicationStatus;
use App\Enums\DocumentType;
use App\Models\Application;
use App\Models\Document;
use App\Models\DownloadAudit;
use App\Models\EmployerProfile;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('documents');
    Storage::fake('logos');
});

/**
 * Whether the qpdf merge backend is available (mirrors the admin download
 * test). Resume tests build real PDFs and skip-with-message when it is absent.
 */
function employerApplicantQpdfAvailable(): bool
{
    try {
        $process = new Process([(string) config('nexus.qpdf_path', 'qpdf'), '--version']);
        $process->run();

        return $process->isSuccessful();
    } catch (ProcessStartFailedException) {
        return false;
    }
}

/**
 * Create an approved employer + a job + an application from a fresh candidate.
 *
 * @return array{user: User, job: JobPost, application: Application}
 */
function employerWithApplicant(): array
{
    $user = User::factory()->employer()->create();
    $profile = EmployerProfile::factory()->approved()->create(['user_id' => $user->id]);
    $job = JobPost::factory()->create(['employer_profile_id' => $profile->id]);
    $candidate = createCandidateWithProfile(['full_name' => 'Ravi Kumar']);
    $application = Application::factory()->create([
        'job_post_id' => $job->id,
        'candidate_profile_id' => $candidate->id,
    ]);

    return ['user' => $user, 'job' => $job, 'application' => $application];
}

/*
| Access control.
*/

it('rejects guests and non-employers on the applicant endpoints', function () {
    ['job' => $job, 'application' => $application] = employerWithApplicant();

    $this->getJson("/api/employer/jobs/{$job->id}/applicants")->assertUnauthorized();
    $this->getJson("/api/employer/applications/{$application->id}/resume")->assertUnauthorized();

    foreach ([User::factory()->candidate()->create(), User::factory()->admin()->create()] as $user) {
        actingAsUser($user)->getJson("/api/employer/jobs/{$job->id}/applicants")->assertForbidden();
        actingAsUser($user)->getJson("/api/employer/applications/{$application->id}/resume")->assertForbidden();
    }
});

/*
| Applicant listing + ownership.
*/

it('lists applicants for the employer\'s own job with a candidate summary', function () {
    ['user' => $user, 'job' => $job] = employerWithApplicant();

    actingAsUser($user)->getJson("/api/employer/jobs/{$job->id}/applicants")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.candidate.full_name', 'Ravi Kumar');
});

it('forbids listing applicants of another employer\'s job', function () {
    ['job' => $job] = employerWithApplicant();

    $otherUser = User::factory()->employer()->create();
    EmployerProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    actingAsUser($otherUser)->getJson("/api/employer/jobs/{$job->id}/applicants")->assertForbidden();
});

/*
| Status updates.
*/

it('updates an application status', function () {
    ['user' => $user, 'application' => $application] = employerWithApplicant();

    actingAsUser($user)->patchJson("/api/employer/applications/{$application->id}/status", [
        'status' => 'shortlisted',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shortlisted');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Shortlisted);
});

it('rejects an invalid application status', function () {
    ['user' => $user, 'application' => $application] = employerWithApplicant();

    actingAsUser($user)->patchJson("/api/employer/applications/{$application->id}/status", [
        'status' => 'hired',
    ])->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('forbids updating the status of an application on another employer\'s job', function () {
    ['application' => $application] = employerWithApplicant();

    $otherUser = User::factory()->employer()->create();
    EmployerProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    actingAsUser($otherUser)->patchJson("/api/employer/applications/{$application->id}/status", [
        'status' => 'selected',
    ])->assertForbidden();
});

/*
| Resume download (the ONLY downloadable artifact).
*/

it('streams a resume pdf and writes a resume audit row with the employer as actor', function () {
    if (! employerApplicantQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    ['user' => $user, 'application' => $application] = employerWithApplicant();

    $response = actingAsUser($user)->get("/api/employer/applications/{$application->id}/resume");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->streamedContent())->toStartWith('%PDF');

    $audit = DownloadAudit::query()->firstOrFail();
    expect($audit->kind)->toBe(DownloadAudit::KIND_RESUME)
        ->and($audit->actor_user_id)->toBe($user->id)
        ->and($audit->candidate_profile_id)->toBe($application->candidate_profile_id);
});

it('forbids downloading the resume for an application on another employer\'s job', function () {
    ['application' => $application] = employerWithApplicant();

    $otherUser = User::factory()->employer()->create();
    EmployerProfile::factory()->approved()->create(['user_id' => $otherUser->id]);

    actingAsUser($otherUser)->getJson("/api/employer/applications/{$application->id}/resume")->assertForbidden();

    expect(DownloadAudit::query()->count())->toBe(0);
});

/*
| Steering: no document/pack/search access for employers.
*/

it('exposes no employer route for candidate documents, packs or search', function () {
    ['user' => $user, 'application' => $application] = employerWithApplicant();
    $candidateProfileId = $application->candidate_profile_id;

    Document::query()->create([
        'candidate_profile_id' => $candidateProfileId,
        'type' => DocumentType::Cv,
        'disk' => 'documents',
        'path' => "documents/{$candidateProfileId}/cv/".Str::uuid()->toString().'.pdf',
        'original_name' => 'cv.pdf',
        'mime' => 'application/pdf',
        'size' => 10,
        'page_count' => null,
        'sort_order' => 0,
        'sha256' => hash('sha256', 'x'),
    ]);

    // None of these employer paths exist: candidate docs, pack, or search.
    actingAsUser($user)->getJson("/api/employer/candidates/{$candidateProfileId}")->assertNotFound();
    actingAsUser($user)->getJson("/api/employer/candidates/{$candidateProfileId}/pack")->assertNotFound();
    actingAsUser($user)->getJson('/api/employer/candidates')->assertNotFound();
    actingAsUser($user)->getJson("/api/employer/applications/{$application->id}/documents")->assertNotFound();
    actingAsUser($user)->getJson("/api/employer/applications/{$application->id}/pack")->assertNotFound();
});
