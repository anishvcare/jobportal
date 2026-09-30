<?php

namespace App\Http\Controllers\Api\Employer;

use App\Enums\EmployerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateEmployerProfileRequest;
use App\Http\Requests\Employer\UploadLogoRequest;
use App\Http\Resources\Employer\EmployerProfileResource;
use App\Models\EmployerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Company profile lives on the public 'logos' disk (logos are
     * non-sensitive); NEVER the private 'documents' disk.
     */
    private const LOGO_DISK = 'logos';

    /**
     * @var list<string>
     */
    private const RELATIONS = ['country', 'state', 'district'];

    public function show(Request $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $profile->load(self::RELATIONS);

        return $this->respond($profile);
    }

    public function update(UpdateEmployerProfileRequest $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        // status/approved_at are admin-controlled and never mass-assigned here.
        $profile->fill($request->validated());
        $profile->save();
        $profile->load(self::RELATIONS);

        return $this->respond($profile);
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $file = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $name = Str::uuid()->toString().'.'.$extension;
        $path = Storage::disk(self::LOGO_DISK)->putFileAs("logos/{$profile->id}", $file, $name);

        // Replace: remove the previous file so orphans do not accumulate.
        $this->deleteExistingLogo($profile);

        $profile->update([
            'logo_disk' => self::LOGO_DISK,
            'logo_path' => $path,
        ]);

        $profile->load(self::RELATIONS);

        return $this->respond($profile);
    }

    public function deleteLogo(Request $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $this->deleteExistingLogo($profile);

        $profile->update([
            'logo_disk' => null,
            'logo_path' => null,
        ]);

        $profile->load(self::RELATIONS);

        return $this->respond($profile);
    }

    /**
     * Wrap the resource in a 200 response. Without this, a freshly created
     * profile (wasRecentlyCreated) would auto-emit a 201 from the resource.
     */
    private function respond(EmployerProfile $profile): JsonResponse
    {
        return (new EmployerProfileResource($profile))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Resolve the employer's own profile, creating an empty pending one on
     * first access. Every employer therefore has exactly one profile.
     */
    private function resolveProfile(Request $request): EmployerProfile
    {
        $user = $request->user();

        return $user->employerProfile
            ?? $user->employerProfile()->create([
                'status' => EmployerStatus::Pending,
            ]);
    }

    private function deleteExistingLogo(EmployerProfile $profile): void
    {
        if ($profile->logo_path !== null) {
            Storage::disk($profile->logo_disk ?? self::LOGO_DISK)->delete($profile->logo_path);
        }
    }
}
