<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Mode démo : pages de clubs, journées de détection et témoignages d'EXEMPLE (marqués comme tels),
 * pour que le site ne soit pas vide. Rien n'est créé quand Supabase est branché.
 */
class DemoSeed
{
    public function __construct(private DataSource $data)
    {
    }

    public function ensure(): void
    {
        if (! $this->data->isDemo()) {
            return;
        }
        try {
            if (! DB::table('organizations')->exists()) {
                $this->organizations();
            }
            if (! DB::table('events')->exists()) {
                $this->events();
            }
            if (! DB::table('success_stories')->exists()) {
                $this->stories();
            }
        } catch (Throwable) {
        }
    }

    private function organizations(): void
    {
        $orgs = [
            ['etoile-de-conakry', 'Étoile de Conakry (exemple)', 'club', 'Football', 'Conakry', 'Club formateur de Ligue 2, réputé pour ses jeunes ailiers.', 'Champion régional U17 2025', '#C8102E', true],
            ['academie-espoir-kindia', 'Académie Espoir de Kindia (exemple)', 'academie', 'Football', 'Kindia', 'Internat sportif et scolaire pour les 12-18 ans.', '6 joueurs passés pro depuis 2020', '#16A34A', true],
            ['fouta-basket-club', 'Fouta Basket Club (exemple)', 'club', 'Basket', 'Labé', 'Équipes féminines et masculines, U15 à seniors.', 'Finaliste de la coupe nationale 2026', '#F2A900', true],
            ['agence-talents-ouest', 'Agence Talents de l’Ouest (exemple)', 'agent', 'Football', 'Dakar', 'Agent licencié FIFA : placement en Afrique du Nord et en Europe.', null, '#1D4ED8', true],
            ['universite-sport-atlantique', 'Université Atlantique – Sport (exemple)', 'universite', 'Athlétisme', 'Abidjan', 'Bourses sportives et études : athlétisme, basket, football.', null, '#7C3AED', false],
            ['judo-club-kankan', 'Judo Club de Kankan (exemple)', 'club', 'Judo', 'Kankan', 'Formation des jeunes judokas de Haute-Guinée.', '3 médailles aux championnats nationaux', '#1E3A8A', false],
        ];
        foreach ($orgs as [$slug, $name, $type, $sport, $city, $desc, $pal, $color, $verified]) {
            DB::table('organizations')->insert([
                'slug' => $slug, 'name' => $name, 'type' => $type, 'sport' => $sport, 'city' => $city, 'country' => in_array($city, ['Dakar']) ? 'Sénégal' : (in_array($city, ['Abidjan']) ? "Côte d'Ivoire" : 'Guinée'),
                'description' => $desc, 'palmares' => $pal, 'color' => $color, 'verified' => $verified, 'demo' => true, 'owner_key' => $slug === 'etoile-de-conakry' ? 'r1' : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function events(): void
    {
        $id = fn ($slug) => DB::table('organizations')->where('slug', $slug)->value('id');
        $events = [
            [$id('etoile-de-conakry'), 'r1', 'Détection U17 – ailiers et attaquants', 'Football', 9, '09:00', 'Stade du quartier (exemple)', 'Conakry', 60, 15, 17, 'Apporte tes crampons, ta pièce d’identité et ton billet. Gratuit.'],
            [$id('academie-espoir-kindia'), null, 'Journée portes ouvertes de l’académie', 'Football', 16, '08:30', 'Terrain de l’académie (exemple)', 'Kindia', 80, 12, 16, 'Tests physiques et techniques, rencontre avec les éducateurs.'],
            [$id('fouta-basket-club'), null, 'Sélection U18 filles et garçons', 'Basket', 23, '15:00', 'Gymnase municipal (exemple)', 'Labé', 40, 15, 18, 'Essai en 3 contre 3 puis 5 contre 5.'],
            [$id('universite-sport-atlantique'), null, 'Tests pour bourses sportives 2027', 'Athlétisme', 30, '07:30', 'Stade d’athlétisme (exemple)', 'Abidjan', 30, 17, 21, 'Chronométrage 100 m / 400 m / 800 m. Bulletin scolaire demandé.'],
        ];
        foreach ($events as [$org, $owner, $title, $sport, $inDays, $time, $place, $city, $cap, $min, $max, $desc]) {
            DB::table('events')->insert([
                'organization_id' => $org, 'owner_key' => $owner, 'title' => $title, 'sport' => $sport, 'date' => now()->addDays($inDays)->toDateString(),
                'time' => $time, 'place' => $place, 'city' => $city, 'capacity' => $cap, 'age_min' => $min, 'age_max' => $max, 'description' => $desc,
                'demo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function stories(): void
    {
        $stories = [
            ['Exemple : Mohamed C.', 'Football', 'Conakry', 'un club de Ligue 1 (exemple)', '2026', 'Un recruteur a vu ma compilation de dribbles un mardi soir. Le samedi, j’étais à l’essai. Aujourd’hui je m’entraîne avec les pros.'],
            ['Exemple : Aminata S.', 'Basket', 'Kindia', 'une université américaine (exemple)', '2026', 'J’ai publié mes highlights et rempli ma fiche. Trois universités m’ont écrit ; j’ai obtenu une bourse d’études complète.'],
            ['Exemple : Ibrahima K.', 'Athlétisme', 'Labé', 'un centre de formation régional (exemple)', '2025', 'Grâce au défi « Le plus rapide », mon 100 m a été vu par l’entraîneur du centre. Il m’a invité aux tests.'],
        ];
        foreach ($stories as [$name, $sport, $city, $by, $year, $text]) {
            DB::table('success_stories')->insert([
                'name' => $name, 'sport' => $sport, 'from_city' => $city, 'recruited_by' => $by, 'year' => $year, 'story' => $text,
                'consent' => true, 'status' => 'publie', 'demo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
