@extends('layouts.app', [
    'title' => 'Notifications - Lycée Privé Pagnidibsom',
    'active' => 'communications',
    'pageTitle' => 'Notifications',
    'pageSubtitle' => 'Emails aux parents et au personnel, historique et suivi du quota Resend',
])

@section('page_actions')
    @can('communications.send')
        <a class="btn {{ $tab === 'send' ? 'btn-primary' : 'btn-subtle' }}" href="{{ route('communications.index', ['tab' => 'send']) }}">Envoyer</a>
    @endcan
    <a class="btn {{ $tab === 'history' ? 'btn-primary' : 'btn-subtle' }}" href="{{ route('communications.index', ['tab' => 'history']) }}">Historique</a>
    <a class="btn {{ $tab === 'templates' ? 'btn-primary' : 'btn-subtle' }}" href="{{ route('communications.index', ['tab' => 'templates']) }}">Modèles</a>
@endsection

@section('content')
    @php
        $statusLabels = [
            'pending' => 'En attente',
            'queued' => 'En queue',
            'deferred' => 'Reporté par quota',
            'sent' => 'Envoyé',
            'failed' => 'Échec',
            'skipped' => 'Ignoré',
            'draft' => 'Brouillon',
            'processing' => 'En cours',
            'completed' => 'Terminé',
            'partial' => 'Partiel',
        ];
        $eventLabels = [
            'announcement' => 'Annonce',
            'payment_received' => 'Paiement',
            'attendance_alert' => 'Absence / retard',
            'student_status_changed' => 'Statut élève',
        ];
        $audienceLabels = [
            'guardians_all' => 'Tous les parents',
            'guardians_class' => 'Parents d’une classe',
            'staff_all' => 'Tout le personnel',
            'staff_role' => 'Personnel par rôle',
        ];
        $roleLabels = [
            'admin' => 'Administration',
            'direction' => 'Direction',
            'secretariat' => 'Secrétariat',
            'comptable' => 'Comptabilité',
            'enseignant' => 'Enseignants',
            'surveillant' => 'Surveillants',
        ];
        $dailyPercent = $quota['daily_usable'] > 0 ? min(100, round(($quota['daily_used'] / $quota['daily_usable']) * 100)) : 0;
        $monthlyPercent = $quota['monthly_limit'] > 0 ? min(100, round(($quota['monthly_used'] / $quota['monthly_limit']) * 100)) : 0;
    @endphp

    @if ($errors->any())
        <section class="panel" style="margin-bottom:16px;border-color:#b42318">
            <strong>Le formulaire contient une erreur.</strong>
            <p style="margin:8px 0 0;color:var(--muted)">{{ $errors->first() }}</p>
        </section>
    @endif

    <section class="summary-row">
        <div class="stat">
            <span>Quota aujourd’hui</span>
            <strong>{{ $quota['daily_used'] }} / {{ $quota['daily_usable'] ?: '∞' }}</strong>
            <div class="meter" style="margin-top:10px"><span style="--value:{{ $dailyPercent }}%"></span></div>
        </div>
        <div class="stat">
            <span>Quota ce mois</span>
            <strong>{{ $quota['monthly_used'] }} / {{ $quota['monthly_limit'] ?: '∞' }}</strong>
            <div class="meter" style="margin-top:10px"><span style="--value:{{ $monthlyPercent }}%"></span></div>
        </div>
        <div class="stat">
            <span>Réserve protégée</span>
            <strong>{{ $quota['reserve'] }} email(s)</strong>
            <small style="color:var(--muted)">Disponible pour les messages prioritaires hors module</small>
        </div>
    </section>

    @if ($tab === 'send')
        @can('communications.send')
            <section class="grid two-col">
                <form class="panel" method="POST" action="{{ route('communications.announcements.store') }}">
                    @csrf
                    <div class="panel-head">
                        <h2>Nouvelle annonce</h2>
                        <span class="badge">Email</span>
                    </div>

                    <div class="form-grid">
                        <div class="field wide">
                            <label for="title">Titre interne</label>
                            <input id="title" name="title" value="{{ old('title') }}" maxlength="150" required placeholder="Réunion des parents">
                            @error('title') <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field wide">
                            <label for="audience">Destinataires</label>
                            <select id="audience" name="audience" required>
                                <option value="guardians_all" @selected(old('audience') === 'guardians_all')>Tous les parents avec email valide ({{ $guardianCount }})</option>
                                <option value="guardians_class" @selected(old('audience') === 'guardians_class')>Parents d’une classe</option>
                                <option value="staff_all" @selected(old('audience') === 'staff_all')>Tout le personnel avec email valide ({{ $staffCount }})</option>
                                <option value="staff_role" @selected(old('audience') === 'staff_role')>Personnel par rôle</option>
                            </select>
                            @error('audience') <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field wide" id="class-audience-field">
                            <label for="school_class_id">Classe</label>
                            <select id="school_class_id" name="school_class_id">
                                <option value="">Choisir une classe</option>
                                @foreach ($classes as $schoolClass)
                                    <option value="{{ $schoolClass->id }}" @selected((int) old('school_class_id') === $schoolClass->id)>
                                        {{ $schoolClass->name }} ({{ $classGuardianCounts[$schoolClass->id] ?? 0 }} email(s))
                                    </option>
                                @endforeach
                            </select>
                            @error('school_class_id') <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field wide" id="role-audience-field">
                            <label for="role_name">Rôle</label>
                            <select id="role_name" name="role_name">
                                <option value="">Choisir un rôle</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}" @selected(old('role_name') === $role->name)>
                                        {{ $roleLabels[$role->name] ?? ucfirst($role->name) }} ({{ $roleStaffCounts[$role->name] ?? 0 }} email(s))
                                    </option>
                                @endforeach
                            </select>
                            @error('role_name') <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field wide">
                            <label for="subject">Objet de l’email</label>
                            <input id="subject" name="subject" value="{{ old('subject') }}" maxlength="200" required>
                            @error('subject') <small class="error">{{ $message }}</small> @enderror
                        </div>

                        <div class="field wide">
                            <label for="body">Message</label>
                            <textarea id="body" name="body" rows="11" maxlength="10000" required>{{ old('body') }}</textarea>
                            <small style="color:var(--muted)">La variable <code>@{{ recipient_name }}</code> peut être utilisée. Le contenu est échappé dans l’email.</small>
                            @error('body') <small class="error">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Mettre en file d’envoi</button>
                    </div>
                </form>

                <div class="panel">
                    <div class="panel-head">
                        <h2>Envois automatiques</h2>
                        <span class="badge">Actifs</span>
                    </div>
                    <div class="detail-grid" style="grid-template-columns:1fr">
                        <div class="detail-item">
                            <span>Paiements</span>
                            <strong>Confirmation et numéro de reçu aux parents</strong>
                        </div>
                        <div class="detail-item">
                            <span>Absences et retards</span>
                            <strong>Alerte à la première modification du pointage</strong>
                        </div>
                        <div class="detail-item">
                            <span>Statut de l’élève</span>
                            <strong>Information lors d’un transfert, abandon, diplôme ou suspension</strong>
                        </div>
                    </div>
                    <p style="margin:16px 0 0;color:var(--muted);line-height:1.6">
                        Les adresses de démonstration et les domaines locaux sont automatiquement exclus.
                        Les erreurs d’email ne bloquent jamais l’opération scolaire à l’origine du message.
                    </p>
                </div>
            </section>
        @else
            <section class="panel" style="margin-top:16px">
                <div class="empty">Votre rôle permet de consulter les envois, mais pas de créer une annonce.</div>
            </section>
        @endcan

        <script>
            (() => {
                const audience = document.getElementById('audience');
                const classField = document.getElementById('class-audience-field');
                const roleField = document.getElementById('role-audience-field');

                if (! audience || ! classField || ! roleField) {
                    return;
                }

                const refresh = () => {
                    classField.style.display = audience.value === 'guardians_class' ? '' : 'none';
                    roleField.style.display = audience.value === 'staff_role' ? '' : 'none';
                };

                audience.addEventListener('change', refresh);
                refresh();
            })();
        </script>
    @elseif ($tab === 'history')
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Campagnes récentes</h2>
                <span class="badge">{{ $campaigns->count() }} campagne(s)</span>
            </div>

            @if ($campaigns->isEmpty())
                <div class="empty">Aucune campagne enregistrée.</div>
            @else
                <div class="subject-list-scroll">
                    <table class="table" style="min-width:900px">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Annonce</th>
                                <th>Public</th>
                                <th>Progression</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campaigns as $campaign)
                                <tr>
                                    <td>{{ $campaign->created_at?->format('d/m/Y H:i') }}</td>
                                    <td><strong>{{ $campaign->title }}</strong><br><span style="color:var(--muted)">{{ $campaign->subject }}</span></td>
                                    <td>{{ $audienceLabels[$campaign->audience] ?? $campaign->audience }}{{ $campaign->schoolClass ? ' - '.$campaign->schoolClass->name : '' }}</td>
                                    <td>{{ $campaign->sent_count }} envoyé(s), {{ $campaign->failed_count }} échec(s), {{ $campaign->skipped_count }} ignoré(s) / {{ $campaign->recipients_count }}</td>
                                    <td><span class="badge {{ in_array($campaign->status, ['failed', 'partial']) ? 'badge-danger' : ($campaign->status === 'processing' ? 'badge-warning' : '') }}">{{ $statusLabels[$campaign->status] ?? $campaign->status }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Journal des emails</h2>
                <span class="badge">{{ $messages->total() }} message(s)</span>
            </div>

            <form class="searchbar" method="GET" action="{{ route('communications.index') }}">
                <input type="hidden" name="tab" value="history">
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, email ou objet">
                <select name="event">
                    <option value="">Tous les événements</option>
                    @foreach ($eventLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['event'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status">
                    <option value="">Tous les statuts</option>
                    @foreach (['queued', 'deferred', 'sent', 'failed', 'skipped'] as $value)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $statusLabels[$value] }}</option>
                    @endforeach
                </select>
                <button class="btn btn-subtle" type="submit">Filtrer</button>
                <a class="btn btn-subtle" href="{{ route('communications.index', ['tab' => 'history']) }}">Réinitialiser</a>
            </form>

            @if ($messages->isEmpty())
                <div class="empty" style="margin-top:16px">Aucun message trouvé.</div>
            @else
                <div class="subject-list-scroll" style="margin-top:16px">
                    <table class="table" style="min-width:1100px">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Destinataire</th>
                                <th>Événement</th>
                                <th>Objet</th>
                                <th>Statut</th>
                                <th>Tentatives</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr>
                                    <td>{{ ($message->sent_at ?? $message->created_at)?->format('d/m/Y H:i') }}</td>
                                    <td><strong>{{ $message->recipient_name }}</strong><br><span style="color:var(--muted)">{{ $message->recipient_email }}</span></td>
                                    <td>{{ $eventLabels[$message->event_type] ?? $message->event_type }}</td>
                                    <td>{{ $message->subject }}@if($message->error_message)<br><small class="error">{{ \Illuminate\Support\Str::limit($message->error_message, 100) }}</small>@endif</td>
                                    <td><span class="badge {{ $message->status === 'failed' ? 'badge-danger' : (in_array($message->status, ['deferred', 'queued']) ? 'badge-warning' : '') }}">{{ $statusLabels[$message->status] ?? $message->status }}</span></td>
                                    <td>{{ $message->attempts }}</td>
                                    <td>
                                        @can('communications.send')
                                            @if (in_array($message->status, ['failed', 'deferred'], true))
                                                <form method="POST" action="{{ route('communications.messages.retry', $message) }}">
                                                    @csrf
                                                    <button class="btn btn-subtle" type="submit">Relancer</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination">{{ $messages->links() }}</div>
            @endif
        </section>
    @else
        <section class="panel" style="margin-top:16px">
            <div class="panel-head">
                <h2>Modèles automatiques</h2>
                <span class="badge">{{ $templates->count() }} modèle(s)</span>
            </div>
            <p style="color:var(--muted)">Les variables entre doubles accolades sont remplacées au moment de créer l’email.</p>
        </section>

        <section class="grid two-col">
            @foreach ($templates as $template)
                <div class="panel">
                    <div class="panel-head">
                        <h2>{{ $template->name }}</h2>
                        <span class="badge {{ $template->is_active ? '' : 'badge-warning' }}">{{ $template->is_active ? 'Actif' : 'Inactif' }}</span>
                    </div>

                    @can('communications.templates.manage')
                        <form method="POST" action="{{ route('communications.templates.update', $template) }}">
                            @csrf
                            @method('PUT')
                            <div class="form-grid">
                                <div class="field wide">
                                    <label for="subject-{{ $template->id }}">Objet</label>
                                    <input id="subject-{{ $template->id }}" name="subject" value="{{ $template->subject }}" required maxlength="200">
                                </div>
                                <div class="field wide">
                                    <label for="body-{{ $template->id }}">Corps du message</label>
                                    <textarea id="body-{{ $template->id }}" name="body" rows="10" required maxlength="10000">{{ $template->body }}</textarea>
                                </div>
                                <div class="field wide">
                                    <label>
                                        <input name="is_active" type="checkbox" value="1" @checked($template->is_active)>
                                        Utiliser ce modèle pour les prochains événements
                                    </label>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit">Enregistrer</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('communications.templates.reset', $template) }}" style="margin-top:10px">
                            @csrf
                            <button class="btn btn-subtle" type="submit">Restaurer le texte initial</button>
                        </form>
                    @else
                        <div class="detail-item">
                            <span>Objet</span>
                            <strong>{{ $template->subject }}</strong>
                        </div>
                        <div class="detail-item" style="margin-top:12px;white-space:pre-line">{{ $template->body }}</div>
                    @endcan

                    <div style="margin-top:14px">
                        @foreach ($template->available_variables ?? [] as $variable)
                            <code class="badge">&#123;&#123; {{ $variable }} &#125;&#125;</code>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>
    @endif
@endsection
