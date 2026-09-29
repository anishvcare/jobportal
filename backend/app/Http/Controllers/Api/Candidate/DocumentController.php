<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Enums\DocumentType;
use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadDocumentRequest;
use App\Http\Resources\Candidate\DocumentResource;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Services\Documents\UploadValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use ResolvesCandidateProfile;

    private const DISK = 'documents';

    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $documents = $profile->documents()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return DocumentResource::collection($documents);
    }

    public function store(UploadDocumentRequest $request, UploadValidator $validator): DocumentResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $type = $request->documentType();
        $file = $request->file('file');

        $meta = $validator->validate($file);

        // Single-value types replace any previous file of the same type.
        if (! DocumentType::allowsMultiple($type)) {
            $this->deleteExistingOfType($profile, $type);
        }

        $document = $this->persist($profile, $type, $file, $meta, $request->input('sort_order'));

        return new DocumentResource($document);
    }

    /**
     * Replace the file behind an existing document (keeps its sort_order).
     */
    public function update(UploadDocumentRequest $request, UploadValidator $validator, Document $document): DocumentResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $document);

        $type = $request->documentType();
        $file = $request->file('file');
        $meta = $validator->validate($file);

        Storage::disk($document->disk)->delete($document->path);

        $path = $this->storeFile($profile, $type, $file);

        $document->update([
            'type' => $type,
            'path' => $path,
            'disk' => self::DISK,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $meta['mime'],
            'size' => $file->getSize(),
            'page_count' => $meta['page_count'],
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        return new DocumentResource($document);
    }

    public function destroy(Request $request, Document $document): Response
    {
        $this->authorize('delete', $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return response()->noContent();
    }

    /**
     * Reorder the candidate's passport documents by the given id order.
     */
    public function reorder(Request $request): AnonymousResourceCollection
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $ids = $validated['order'];

        $passportIds = $profile->documents()
            ->where('type', DocumentType::Passport->value)
            ->pluck('id')
            ->all();

        // Every id must belong to this profile and be a passport document.
        abort_unless(
            count($ids) === count($passportIds) && empty(array_diff($ids, $passportIds)),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'The order must contain exactly this profile\'s passport documents.'
        );

        DB::transaction(function () use ($ids, $profile) {
            foreach ($ids as $index => $id) {
                $profile->documents()
                    ->where('id', $id)
                    ->update(['sort_order' => $index]);
            }
        });

        $documents = $profile->documents()
            ->where('type', DocumentType::Passport->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return DocumentResource::collection($documents);
    }

    /**
     * Stream the owner's document from its private disk.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime]
        );
    }

    private function deleteExistingOfType(CandidateProfile $profile, DocumentType $type): void
    {
        foreach ($profile->documents()->where('type', $type->value)->get() as $existing) {
            Storage::disk($existing->disk)->delete($existing->path);
            $existing->delete();
        }
    }

    /**
     * @param  array{mime:string, page_count:int|null}  $meta
     */
    private function persist(
        CandidateProfile $profile,
        DocumentType $type,
        UploadedFile $file,
        array $meta,
        mixed $sortOrder
    ): Document {
        $path = $this->storeFile($profile, $type, $file);

        $order = $sortOrder !== null
            ? (int) $sortOrder
            : (int) $profile->documents()->where('type', $type->value)->max('sort_order') + 1;

        return $profile->documents()->create([
            'type' => $type,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $meta['mime'],
            'size' => $file->getSize(),
            'page_count' => $meta['page_count'],
            'sort_order' => $order,
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);
    }

    private function storeFile(
        CandidateProfile $profile,
        DocumentType $type,
        UploadedFile $file
    ): string {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $name = Str::uuid()->toString().'.'.$extension;
        $directory = "documents/{$profile->id}/{$type->value}";

        return Storage::disk(self::DISK)->putFileAs($directory, $file, $name);
    }
}
