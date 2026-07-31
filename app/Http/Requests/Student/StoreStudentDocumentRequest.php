<?php

namespace App\Http\Requests\Student;

use App\Services\RequiredStudentDocumentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('students.update') ?? false;
    }

    public function rules(RequiredStudentDocumentService $requiredDocuments): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', Rule::in(array_keys($requiredDocuments->availableDocumentTypes()))],
            'status' => ['required', 'in:received,missing,expired'],
            'received_at' => ['nullable', 'date'],
            'document_file' => ['required_unless:status,missing', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_file.required_unless' => 'Ajoute un fichier PDF ou image, ou marque le document comme manquant.',
            'document_file.mimes' => 'Le fichier doit etre un PDF ou une image JPG, PNG ou WebP.',
            'document_file.max' => 'Le fichier ne doit pas depasser 10 Mo.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->session()->flash('student_document_open', true);

        parent::failedValidation($validator);
    }
}
