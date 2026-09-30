<?php

namespace App\Http\Resources\Employer;

use App\Models\EmployerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin EmployerProfile
 */
class EmployerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'website' => $this->website,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'city' => $this->city,
            'country' => $this->whenLoaded('country', fn () => $this->country?->name),
            'state' => $this->whenLoaded('state', fn () => $this->state?->name),
            'district' => $this->whenLoaded('district', fn () => $this->district?->name),
            'status' => $this->status->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'logo_url' => $this->logo_path !== null
                ? Storage::disk($this->logo_disk ?? 'logos')->url($this->logo_path)
                : null,
        ];
    }
}
