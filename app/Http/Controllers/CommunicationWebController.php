<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationMessage;
use App\Models\CommunicationTemplate;
use App\Models\SchoolClass;
use App\Services\CommunicationQuotaService;
use App\Services\CommunicationRecipientService;
use App\Services\CommunicationService;
use App\Services\CommunicationTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class CommunicationWebController extends Controller
{
    public function index(
        Request $request,
        CommunicationTemplateService $templates,
        CommunicationRecipientService $recipients,
        CommunicationQuotaService $quota,
    ): View {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();
        $classes = SchoolClass::query()
            ->when($academicYear, fn ($query) => $query->where('academic_year_id', $academicYear->id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $roles = Role::query()
            ->whereNotIn('name', ['parent', 'eleve'])
            ->orderBy('name')
            ->get();

        $messages = CommunicationMessage::query()
            ->with(['campaign', 'creator'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('delivery')->toString(), fn ($query, string $status) => $query->where('delivery_status', $status))
            ->when($request->string('event')->toString(), fn ($query, string $event) => $query->where('event_type', $event))
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('communications.index', [
            'academicYear' => $academicYear,
            'classes' => $classes,
            'guardianCount' => $recipients->guardians($academicYear)->count(),
            'classGuardianCounts' => $classes->mapWithKeys(
                fn (SchoolClass $schoolClass) => [$schoolClass->id => $recipients->guardians($academicYear, $schoolClass->id)->count()],
            ),
            'staffCount' => $recipients->staff()->count(),
            'roleStaffCounts' => $roles->mapWithKeys(
                fn (Role $role) => [$role->name => $recipients->staff($role->name)->count()],
            ),
            'roles' => $roles,
            'messages' => $messages,
            'campaigns' => CommunicationCampaign::query()
                ->with(['creator', 'schoolClass'])
                ->withCount([
                    'messages as delivered_count' => fn ($query) => $query->where('delivery_status', 'delivered'),
                    'messages as delivery_problem_count' => fn ($query) => $query->whereIn(
                        'delivery_status',
                        ['bounced', 'complained', 'rejected'],
                    ),
                ])
                ->latest()
                ->limit(12)
                ->get(),
            'templates' => $templates->ensureDefaults(),
            'quota' => $quota->usage(),
            'filters' => $request->only(['status', 'delivery', 'event', 'search']),
            'tab' => $request->string('tab')->toString() ?: 'send',
        ]);
    }

    public function storeAnnouncement(
        Request $request,
        CommunicationService $communications,
    ): RedirectResponse {
        $academicYear = AcademicYear::query()->where('is_active', true)->first();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'audience' => ['required', Rule::in(['guardians_all', 'guardians_class', 'staff_all', 'staff_role'])],
            'school_class_id' => [
                Rule::requiredIf($request->input('audience') === 'guardians_class'),
                'nullable',
                Rule::exists('school_classes', 'id')
                    ->when($academicYear, fn ($rule) => $rule->where('academic_year_id', $academicYear->id)),
            ],
            'role_name' => [
                Rule::requiredIf($request->input('audience') === 'staff_role'),
                'nullable',
                Rule::exists('roles', 'name')->whereNot('name', 'parent')->whereNot('name', 'eleve'),
            ],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $campaign = $communications->createAnnouncement($request->user(), $academicYear, $data);

        $message = $campaign->recipients_count === 0
            ? 'Annonce enregistrée, mais aucune adresse email réelle et valide ne correspond à ce public.'
            : "Annonce mise en queue pour {$campaign->recipients_count} destinataire(s).";

        return redirect()
            ->route('communications.index', ['tab' => $campaign->recipients_count === 0 ? 'send' : 'history'])
            ->with('success', $message);
    }

    public function retry(
        CommunicationMessage $message,
        CommunicationService $communications,
    ): RedirectResponse {
        $communications->retry($message);

        return redirect()
            ->route('communications.index', ['tab' => 'history'])
            ->with('success', 'Le message a été remis dans la file d’envoi.');
    }

    public function updateTemplate(
        Request $request,
        CommunicationTemplate $template,
    ): RedirectResponse {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'subject' => trim((string) preg_replace('/[\r\n]+/', ' ', $data['subject'])),
            'body' => $data['body'],
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('communications.index', ['tab' => 'templates'])
            ->with('success', "Le modèle « {$template->name} » a été mis à jour.");
    }

    public function resetTemplate(
        CommunicationTemplate $template,
        Request $request,
        CommunicationTemplateService $templates,
    ): RedirectResponse {
        $templates->reset($template, $request->user());

        return redirect()
            ->route('communications.index', ['tab' => 'templates'])
            ->with('success', "Le modèle « {$template->name} » a été restauré.");
    }
}
