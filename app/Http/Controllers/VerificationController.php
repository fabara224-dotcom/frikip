<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Members;
use App\Terrain\Growth\Verifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Demande de badge vérifié : pièce d'identité ou licence (sportif), licence de club / d'agent (recruteur),
 * et pour un mineur : nom, téléphone, pièce et accord du parent ou tuteur. Validation dans l'espace admin.
 */
class VerificationController extends Controller
{
    public function __construct(private DataSource $data, private Members $members, private Verifications $verifications)
    {
    }

    public function show(): View
    {
        $u = $this->data->user();
        $a = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;

        return view('web.verification', [
            'last' => $this->verifications->latestFor($u['id']),
            'minor' => $a && $a['age'] < 18,
            'athlete' => $a,
            'alreadyVerified' => ! empty($u['verified']) && ! $u['athlete_id'] || ($a['verified'] ?? false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $u = $this->data->user();
        $a = $u['athlete_id'] ? $this->data->athlete($u['athlete_id']) : null;
        $minor = $a && $a['age'] < 18;
        $doc = ['required', 'file', 'max:6144', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,application/pdf'];
        $rules = [
            'full_name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'in:cni,passeport,licence,acte,carte_scolaire,licence_agent,statuts_club'],
            'document' => $doc,
            'licence_number' => [$u['role'] === 'recruiter' ? 'required' : 'nullable', 'string', 'max:80'],
            'organization' => [$u['role'] === 'recruiter' ? 'required' : 'nullable', 'string', 'max:150'],
        ];
        if ($minor) {
            $rules += [
                'guardian_name' => ['required', 'string', 'max:150'],
                'guardian_phone' => ['required', 'string', 'max:30'],
                'guardian_document' => $doc,
                'guardian_consent' => ['accepted'],
            ];
        }
        $data = $request->validate($rules, [
            'document.required' => 'Ajoute une photo lisible du document.',
            'document.mimetypes' => 'Photo (JPG, PNG) ou PDF uniquement.',
            'guardian_consent.accepted' => "L'accord du parent ou tuteur est obligatoire pour un mineur.",
            'licence_number.required' => 'Indique ton numéro de licence (club, fédération ou agent FIFA).',
        ]);
        $this->members->touch();
        $dir = 'verifications/'.Str::slug($u['id']);
        DB::table('verification_requests')->insert([
            'user_key' => $u['id'], 'role' => $u['role'], 'full_name' => $data['full_name'], 'document_type' => $data['document_type'],
            'document_path' => $request->file('document')->store($dir, 'local'),
            'licence_number' => $data['licence_number'] ?? null, 'organization' => $data['organization'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null, 'guardian_phone' => $data['guardian_phone'] ?? null,
            'guardian_document_path' => $minor ? $request->file('guardian_document')->store($dir, 'local') : null,
            'guardian_consent' => $minor && $request->boolean('guardian_consent'),
            'status' => 'en_attente', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('verification')->with('status', 'Demande envoyée. Réponse sous 72 h, tu seras prévenu.');
    }
}
