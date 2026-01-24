<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Validate user profile customization updates.
 *
 * This request handles:
 * - avatar upload (images only; GIF reserved to admins),
 * - avatar frame color (hex format).
 */
class UpdateUserProfileRequest extends FormRequest
{
    /**
     * Determine if the authenticated user is allowed to perform this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['nullable', 'file', 'max:5120'],
            'avatar_frame_color' => ['nullable', 'string', 'max:32', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $file = $this->file('avatar');
            if (! $file instanceof UploadedFile) {
                return;
            }

            $mime = $file->getMimeType() ?? '';
            if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                $validator->errors()->add('avatar', 'The avatar must be an image file.');
                return;
            }

            if ($mime === 'image/gif') {
                $user = $this->user();
                $isAdmin = (bool) ($user?->getAttribute('is_admin') ?? false);
                if (! $isAdmin) {
                    $validator->errors()->add('avatar', 'GIF avatars are reserved for administrators.');
                }
            }
        });
    }
}
