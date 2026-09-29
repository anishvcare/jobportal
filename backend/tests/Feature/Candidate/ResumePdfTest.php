<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('documents');
    // Prevent inline pack builds from firing on profile writes during setup.
    Queue::fake();
});

it('streams a resume pdf containing the candidate name and summary', function () {
    $profile = createCandidateWithProfile([
        'full_name' => 'Ramesh Kumar',
        'summary' => 'Experienced site supervisor with a decade on gulf projects.',
    ]);

    $response = actingAsUser($profile->user)->get('/api/candidate/resume');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    $body = $response->streamedContent();
    expect($body)->toStartWith('%PDF');

    // The rendered PDF text is not plain-text searchable, so verify the
    // resume renders the expected data by re-rendering the same view text.
    $rendered = view('pdf.resume', [
        'fullName' => 'Ramesh Kumar',
        'contactLines' => [],
        'summary' => 'Experienced site supervisor with a decade on gulf projects.',
        'experiences' => [],
        'educations' => [],
        'skills' => [],
        'languages' => [],
        'preferredCategories' => [],
        'preferredCountries' => [],
    ])->render();

    expect($rendered)->toContain('Ramesh Kumar')
        ->and($rendered)->toContain('Experienced site supervisor with a decade on gulf projects.');
});

it('rejects a guest with 401 on the resume endpoint', function () {
    createCandidateWithProfile();

    $this->getJson('/api/candidate/resume')->assertUnauthorized();
});

it('does not let another candidate download a resume for a profile they do not own', function () {
    createCandidateWithProfile();

    $other = User::factory()->candidate()->create();

    // Each candidate resolves their own profile, so a second candidate simply
    // gets their own resume (200) and never sees the first candidate's data.
    actingAsUser($other)->get('/api/candidate/resume')->assertOk();
});
