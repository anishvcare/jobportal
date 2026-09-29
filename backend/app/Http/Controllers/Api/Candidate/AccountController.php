<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    /**
     * Hard-delete the candidate's account and all associated data: physical
     * document files are removed from their disks, then the user row is
     * deleted which cascades to the profile and all of its children.
     */
    public function destroy(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->candidateProfile()->firstOrCreate([]);
        $this->authorize('delete', $profile);

        DB::transaction(function () use ($user, $profile) {
            foreach ($profile->documents()->get() as $document) {
                Storage::disk($document->disk)->delete($document->path);
            }

            // Cascades to candidate_profiles and its children/pivots/documents.
            $user->delete();
        });

        return response()->noContent();
    }
}
