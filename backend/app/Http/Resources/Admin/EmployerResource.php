<?php

namespace App\Http\Resources\Admin;

use App\Models\EmployerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployerProfile
 */
class EmployerResource extends JsonResource
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
            'city' => $this->city,
            'country' => $this->whenLoaded('country', fn () => $this->country?->name),
            'state' => $this->whenLoaded('state', fn () => $this->state?->name),
            'district' => $this->whenLoaded('district', fn () => $this->district?->name),
            'status' => $this->status->value,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'job_count' => $this->when(
                $this->job_posts_count !== null,
                fn () => (int) $this->job_posts_count,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
