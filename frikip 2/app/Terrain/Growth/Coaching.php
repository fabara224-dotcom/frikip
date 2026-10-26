<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Coaching Frikip.
 *  - GRATUIT : à partir du poste et des chiffres de la saison passée, un diagnostic et un programme de 4 semaines
 *    (ex. attaquant avec 3 buts en 20 matchs → finition et déplacements dans la surface).
 *  - PAYANT : suivi par un coach (demandes, programmes, bilans) — formules dans config terrain.offers.coaching.
 */
class Coaching
{
    /** Formules payantes par défaut (GNF) : [libellé, prix, jours, précision]. Modifiables dans config/terrain.php (offers.coaching). */
    public const DEFAULT_OFFERS = [
        ['Bilan vidéo', 50000, 0, 'Un coach regarde tes vidéos et te répond avec 3 points à travailler et tes exercices.'],
        ['Suivi 1 mois', 200000, 30, 'Programme sur mesure, réponses à tes questions, un bilan chaque semaine.'],
        ['Suivi saison', 900000, 120, '4 mois de suivi, programme ajusté chaque semaine, conseils pour tes essais en club.'],
    ];

    public const DEFAULT_OFFERS_XOF = [
        ['Bilan vidéo', 3500, 0, 'Un coach regarde tes vidéos et te répond avec 3 points à travailler et tes exercices.'],
        ['Suivi 1 mois', 13500, 30, 'Programme sur mesure, réponses à tes questions, un bilan chaque semaine.'],
        ['Suivi saison', 60000, 120, '4 mois de suivi, programme ajusté chaque semaine, conseils pour tes essais en club.'],
    ];

    public const STATUS = [
        'attente_paiement' => 'En attente de paiement', 'payee' => 'Payé – un coach arrive', 'en_cours' => 'Suivi en cours',
        'terminee' => 'Terminé', 'annulee' => 'Annulé',
    ];

    public const GOALS = [
        'Marquer plus de buts', 'Être titulaire', 'Gagner en vitesse', 'Tenir 90 minutes', 'Mieux défendre',
        'Préparer un essai en club', 'Revenir de blessure', 'Progresser techniquement',
    ];

    /** Notes que le sportif se donne (1 à 5) : elles affinent les priorités. */
    public const QUALITIES = ['vitesse' => 'Vitesse', 'endurance' => 'Endurance', 'force' => 'Force et duels', 'technique' => 'Technique'];

    /** Chiffres demandés selon le sport : clé => [libellé, aide]. */
    public static function statFields(string $sport, ?string $position = null): array
    {
        if ($sport === 'Football') {
            $f = [
                'matchs' => ['Matchs joués', 'la saison passée'],
                'minutes' => ['Minutes par match (environ)', 'ex. 70'],
                'buts' => ['Buts marqués', 'sur toute la saison'],
                'passes' => ['Passes décisives', 'sur toute la saison'],
            ];
            if ($position === 'Gardien') {
                $f = ['matchs' => $f['matchs'], 'minutes' => $f['minutes'],
                    'encaisses' => ['Buts encaissés', 'sur toute la saison'], 'clean' => ['Matchs sans encaisser', 'clean sheets']];
            }

            return $f + ['cartons' => ['Cartons (jaunes + rouges)', 'sur toute la saison']];
        }
        if ($sport === 'Basket') {
            return ['matchs' => ['Matchs joués', 'la saison passée'], 'points' => ['Points par match', ''], 'rebonds' => ['Rebonds par match', ''],
                'passes' => ['Passes par match', ''], 'tirs' => ['Réussite aux tirs (%)', 'ex. 40'], 'lf' => ['Réussite aux lancers francs (%)', 'ex. 65']];
        }
        if ($sport === 'Handball') {
            return ['matchs' => ['Matchs joués', ''], 'buts' => ['Buts sur la saison', ''], 'tirs' => ['Réussite aux tirs (%)', 'ex. 50']];
        }

        return ['competitions' => ['Compétitions ou combats', 'la saison passée'], 'victoires' => ['Victoires ou podiums', ''], 'defaites' => ['Défaites ou contre-performances', '']];
    }

    /** Postes proposés (repris des fiches de sport). */
    public static function positions(string $sport): array
    {
        foreach (\App\Terrain\SportFields::FIELDS[$sport] ?? [] as $f) {
            if (in_array($f['label'], ['Poste', 'Discipline', 'Catégorie', 'Nage', 'Catégorie de poids'], true) && ! empty($f['options'])) {
                return $f['options'];
            }
        }

        return [];
    }

    // ------------------------------------------------------------------ bibliothèque d'exercices

    /** thème => [titre, [ [exercice, dose, comment le faire], … ], test pour mesurer le progrès] */
    public const THEMES = [
        'finition' => ['Finition devant le but', [
            ['Tirs après contrôle', '4 séries de 8 tirs', 'Un partenaire te passe le ballon à 16 m. Un contrôle orienté, tir du pied fort dans un coin. Vise les 4 coins à tour de rôle.'],
            ['Tirs en une touche', '3 séries de 10 tirs', 'Centres ou passes en retrait depuis le côté : reprise directe sans contrôle, au premier ou au second poteau.'],
            ['Face au gardien', '3 séries de 6 duels', 'Départ à 30 m avec le ballon, frappe ou dribble du gardien en moins de 5 secondes. Garde la tête levée.'],
            ['Pied faible', '3 séries de 10 frappes', 'Mêmes tirs, uniquement du pied faible, en visant le cadre avant la puissance.'],
        ], 'Sur 20 tirs à 16 m (5 par coin), compte combien entrent. Refais le test dans 4 semaines.'],
        'deplacements' => ['Appels et placement dans la surface', [
            ['Appel en profondeur', '6 répétitions × 2', 'Pars dans le dos d’un défenseur (plot) au moment où le passeur lève la tête. Reste en ligne jusqu’à la passe.'],
            ['Premier poteau / second poteau', '3 séries de 8 centres', 'Un partenaire centre : attaque le premier poteau, puis le second. Le dernier pas se fait au moment du centre.'],
            ['Regarder avant de recevoir', 'pendant tous tes matchs', 'Avant chaque ballon, tourne la tête 2 fois pour voir défenseurs et gardien. Demande à un ami de compter dans un match filmé.'],
        ], 'Dans un match filmé, compte tes tirs : objectif 1 tir de plus par match qu’avant.'],
        'passe' => ['Dernière passe et vision du jeu', [
            ['Passes dans la course', '4 séries de 10 passes', 'Un partenaire court en ligne droite : passe-lui le ballon devant lui, sans qu’il ralentisse. Alterne pied droit et gauche.'],
            ['Rondo 4 contre 1', '4 × 3 minutes', 'Deux touches maximum. Cherche toujours le joueur le plus loin du défenseur.'],
            ['Centres en retrait', '3 séries de 8', 'Déborde jusqu’à la ligne de but puis passe en retrait au point de penalty, ballon au sol.'],
        ], 'Sur 20 passes longues vers une zone de 3 m, compte les réussies.'],
        'vitesse' => ['Vitesse et explosivité', [
            ['Sprints courts', '8 × 20 m, 1 minute de repos', 'Départ arrêté, 100 % de ta vitesse. Récupère bien entre chaque sprint : la qualité compte plus que le nombre.'],
            ['Départs à réaction', '6 × 10 m', 'Un partenaire tape dans les mains ou montre une direction : démarre le plus vite possible.'],
            ['Montées de genoux et talons-fesses', '3 × 20 m chacun', 'Échauffement de coordination avant chaque séance.'],
        ], 'Chronomètre ton 30 m départ arrêté (meilleur de 3 essais).'],
        'endurance' => ['Endurance : tenir tout le match', [
            ['Course continue', '25 à 35 minutes', 'Allure où tu peux encore parler. Ajoute 5 minutes chaque semaine.'],
            ['Fractionné 30/30', '2 blocs de 8 répétitions', '30 secondes vite, 30 secondes en trottinant. 3 minutes de marche entre les deux blocs.'],
            ['Navettes', '6 × 4 allers-retours de 20 m', 'Sans t’arrêter, touche la ligne à chaque fois. 1 minute 30 de repos.'],
        ], 'Fais le test Cooper : distance parcourue en 12 minutes.'],
        'force' => ['Force, duels et gainage', [
            ['Gainage', '3 × 45 secondes face + 2 × 30 secondes chaque côté', 'Corps bien droit, ventre serré, ne bloque pas la respiration.'],
            ['Squats et fentes', '3 × 12 squats + 3 × 10 fentes par jambe', 'Sans charge au début. Descends lentement, remonte vite.'],
            ['Pompes', '3 × 10 à 15', 'Sur les genoux si c’est trop dur. Le dos reste droit.'],
            ['Épaule contre épaule', '6 × 20 secondes', 'Avec un partenaire, ballon entre vous : protège le ballon avec le corps, sans les mains.'],
        ], 'Combien de secondes tiens-tu le gainage face ? Combien de pompes d’affilée ?'],
        'technique' => ['Technique et conduite de balle', [
            ['Slalom entre plots', '6 passages', '6 plots espacés d’1 m : intérieur, extérieur, semelle. Puis le même trajet en accélérant.'],
            ['Jonglage', '10 minutes', 'Pied droit, pied gauche, cuisse, tête. Note ton record et essaie de le battre chaque semaine.'],
            ['Contrôles orientés', '3 séries de 10', 'Le ballon arrive : contrôle d’une touche pour partir directement à droite ou à gauche.'],
        ], 'Temps pour faire le slalom aller-retour avec le ballon (meilleur de 3).'],
        'defense' => ['Défense et placement', [
            ['Face à face 1 contre 1', '8 duels', 'Reste sur tes appuis, de profil, pousse l’attaquant vers l’extérieur. N’attaque le ballon qu’au bon moment.'],
            ['Pas chassés', '6 × 15 m', 'Déplacement latéral rapide sans croiser les jambes, puis sprint arrière.'],
            ['Jeu de tête défensif', '3 séries de 8', 'Ballons lancés haut : saute avec un pas d’élan, renvoie loin et haut, vers les côtés.'],
        ], 'Dans un match filmé, compte les duels gagnés sur 10.'],
        'discipline' => ['Discipline et calme', [
            ['Tacles debout', '3 séries de 6', 'Gagne le ballon sans aller au sol : à chaque faute évitée, l’équipe ne perd pas un joueur.'],
            ['Respiration avant les moments chauds', 'avant chaque match', '3 respirations lentes (4 secondes pour inspirer, 6 pour expirer) avant le coup d’envoi et après une décision de l’arbitre.'],
        ], 'Objectif : un carton de moins sur les 10 prochains matchs.'],
        'gardien_reflexes' => ['Réflexes et arrêts (gardien)', [
            ['Tirs rapprochés', '3 séries de 10', 'Un partenaire tire à 8 m, enchaîné : reste sur tes appuis, mains devant.'],
            ['Plongeons au sol', '3 séries de 6 par côté', 'Ballon roulé à 1,5 m de toi : pas d’écart, plongeon, ballon bloqué contre la poitrine.'],
            ['Balle de tennis contre le mur', '5 minutes', 'Lance et rattrape d’une main, de plus en plus vite.'],
        ], 'Sur 20 tirs à 11 m, combien d’arrêts ?'],
        'gardien_placement' => ['Placement et sorties (gardien)', [
            ['Angles fermés', '3 séries de 8', 'Le tireur se place à différents endroits : place-toi sur la ligne ballon–milieu du but avant chaque tir.'],
            ['Sorties aériennes', '3 séries de 8 centres', 'Appelle « gardien ! » fort, prends le ballon au point le plus haut, genou levé pour te protéger.'],
            ['Relances', '3 séries de 10', 'Relance à la main et au pied vers une zone de 5 m à 30 m.'],
        ], 'Sur 10 centres, combien de ballons captés proprement ?'],
        'tir_basket' => ['Tir (basket)', [
            ['Tirs à 5 positions', '5 × 10 tirs', 'Coins, ailes et axe à mi-distance. Même geste à chaque fois : pieds, coude sous le ballon, poignet cassé.'],
            ['Tirs après dribble', '4 × 8 tirs', 'Un dribble à droite ou à gauche puis tir en suspension.'],
            ['Lancers francs fatigué', '10 séries de 2', 'Sprint d’un bout à l’autre du terrain puis 2 lancers francs : comme en fin de match.'],
        ], 'Sur 50 tirs à mi-distance, combien de paniers ?'],
        'rebond' => ['Rebond et jeu près du panier', [
            ['Écran retard', '3 séries de 8', 'Au tir, trouve ton adversaire, mets-le dans ton dos, puis va chercher le ballon des deux mains.'],
            ['Sauts répétés au panneau', '3 × 10 touches', 'Saute et touche le panneau sans t’arrêter entre deux sauts.'],
        ], 'Mesure ta détente : hauteur touchée au mur debout puis en sautant.'],
        'dribble_basket' => ['Dribble et passes (basket)', [
            ['Dribble des deux mains', '5 minutes', 'Changements de main, entre les jambes, dans le dos, sans regarder le ballon.'],
            ['Passes contre le mur', '3 × 30 secondes', 'Passe à deux mains poitrine puis à une main, le plus vite possible.'],
        ], 'Temps pour traverser le terrain en dribble, aller-retour, main faible au retour.'],
        'combat' => ['Technique et tactique en compétition', [
            ['Révision de tes défaites', '1 séance par semaine', 'Regarde la vidéo d’un combat ou d’une course perdue : note 2 erreurs et un exercice pour chacune.'],
            ['Répétitions techniques', '5 × 2 minutes', 'Ta technique principale répétée à vitesse de match avec un partenaire.'],
            ['Séquences à haute intensité', '6 × 30 secondes', 'Effort maximal, 1 minute de repos : comme un moment décisif en compétition.'],
        ], 'Note tes résultats sur les 5 prochaines compétitions.'],
        'mental' => ['Confiance et routine de match', [
            ['Routine d’avant-match', 'à chaque match', 'Même échauffement, même musique, 3 objectifs simples écrits sur ton téléphone (ex. « 3 tirs cadrés »).'],
            ['Revoir tes bonnes actions', '10 minutes avant chaque match', 'Regarde ta propre compilation Frikip : ton cerveau garde les bons gestes.'],
        ], 'Après chaque match, note-toi sur 10 : as-tu atteint tes 3 objectifs ?'],
    ];

    // ------------------------------------------------------------------ plan gratuit

    /**
     * Calcule le diagnostic et le programme.
     *
     * @param  array<string, mixed>  $stats    chiffres saisis
     * @param  array<string, int>    $ratings  notes de 1 à 5 sur QUALITIES
     */
    public function plan(string $sport, ?string $position, array $stats, array $ratings = [], ?string $goal = null): array
    {
        $n = fn (string $k) => max(0, (float) str_replace(',', '.', (string) ($stats[$k] ?? 0)));
        $findings = [];
        $scores = [];       // thème => urgence (plus c'est haut, plus c'est prioritaire)
        $good = [];
        $add = function (string $theme, int $w) use (&$scores) {
            $scores[$theme] = ($scores[$theme] ?? 0) + $w;
        };

        $pos = $position ? mb_strtolower(\App\Terrain\DataLang::t($position)) : '';
        if ($sport === 'Football') {
            $m = max(1, $n('matchs'));
            $min = $n('minutes');
            $gpm = $n('buts') / $m;
            $apm = $n('passes') / $m;
            $fmt = fn ($x) => app()->getLocale() === 'en' ? (string) round($x, 2) : str_replace('.', ',', (string) round($x, 2));
            [$gGood, $gOk, $aGood, $aOk] = match ($position) {
                'Attaquant' => [0.5, 0.25, 0.25, 0.1],
                'Ailier' => [0.3, 0.15, 0.3, 0.15],
                'Milieu offensif' => [0.25, 0.1, 0.35, 0.15],
                'Milieu défensif' => [0.1, 0.03, 0.12, 0.05],
                'Latéral' => [0.08, 0.02, 0.2, 0.08],
                'Défenseur central' => [0.08, 0.02, 0.05, 0.0],
                default => [0, 0, 0, 0],
            };
            if ($position === 'Gardien') {
                $ga = $n('encaisses') / $m;
                $cs = $n('clean') / $m;
                $findings[] = __('Tu encaisses :g but par match et tu gardes ta cage inviolée dans :p % des matchs.', ['g' => $fmt($ga), 'p' => round($cs * 100)]);
                if ($ga > 1.5 || $cs < 0.2) {
                    $findings[] = 'C’est au-dessus de ce qu’attendent les recruteurs pour un gardien titulaire (environ 1 but par match ou moins).';
                    $add('gardien_reflexes', 5);
                    $add('gardien_placement', 5);
                } elseif ($ga > 1.0 || $cs < 0.35) {
                    $findings[] = 'C’est correct ; le placement et les sorties feront la différence.';
                    $add('gardien_placement', 4);
                    $add('gardien_reflexes', 2);
                } else {
                    $good[] = 'Tes chiffres de gardien sont très bons : montre-les dans ta compilation (arrêts, sorties, relances).';
                    $add('gardien_placement', 2);
                }
                $add('force', 1);
            } elseif ($position) {
                $findings[] = __('Avec :b but(s) et :a passe(s) décisive(s) en :m match(s), tu marques :gpm but par match et tu fais :apm passe décisive par match.', ['b' => $n('buts'), 'a' => $n('passes'), 'm' => (int) $m, 'gpm' => $fmt($gpm), 'apm' => $fmt($apm)]);
                if ($gGood > 0.1) {
                    if ($gpm < $gOk) {
                        $findings[] = __('Pour un :pos, les recruteurs regardent au moins :ok but par match ; un bon niveau, c’est :good. C’est ta priorité.', ['pos' => $pos, 'ok' => $fmt($gOk), 'good' => $fmt($gGood)]);
                        $add('finition', 6);
                        $add('deplacements', 5);
                        $add('mental', 1);
                    } elseif ($gpm < $gGood) {
                        $findings[] = __('C’est correct pour un :pos ; pour passer au niveau supérieur, vise :good but par match.', ['pos' => $pos, 'good' => $fmt($gGood)]);
                        $add('finition', 4);
                        $add('deplacements', 2);
                    } else {
                        $good[] = 'Ton efficacité devant le but est très bonne pour ton poste.';
                    }
                }
                if ($aGood > 0.1) {
                    if ($apm < $aOk) {
                        $findings[] = __('Tes passes décisives sont en dessous de ce qu’on attend d’un :pos.', ['pos' => $pos]);
                        $add('passe', 4);
                    } elseif ($apm >= $aGood) {
                        $good[] = 'Tu fais beaucoup marquer tes coéquipiers : c’est un vrai point fort.';
                    } else {
                        $add('passe', 2);
                    }
                }
                if (in_array($position, ['Défenseur central', 'Latéral', 'Milieu défensif'], true)) {
                    $add('defense', 5);
                    $add('force', 3);
                    $findings[] = 'Pour ton poste, les recruteurs regardent surtout les duels gagnés, le placement et la relance.';
                }
            }
            if ($min > 0 && $min < 45) {
                $findings[] = __('Tu joues environ :n minutes par match : gagner du temps de jeu passe par l’endurance et l’intensité à l’entraînement.', ['n' => (int) $min]);
                $add('endurance', 4);
            } elseif ($min >= 80) {
                $good[] = 'Tu joues presque tous les matchs en entier : ton coach te fait confiance.';
            }
            if ($n('matchs') > 0 && $n('matchs') < 8) {
                $findings[] = 'Tu as joué peu de matchs : les chiffres sont indicatifs. Cherche plus de temps de jeu (tournois, matchs amicaux).';
                $add('endurance', 2);
            }
            if ($n('cartons') / $m > 0.25) {
                $findings[] = 'Tu prends beaucoup de cartons : les clubs s’en méfient.';
                $add('discipline', 3);
            }
            $add('vitesse', 2);
            $add('technique', 1);
        } elseif ($sport === 'Basket') {
            $pts = $n('points');
            $tirs = $n('tirs');
            $lf = $n('lf');
            $findings[] = __('Tu marques :p points, prends :r rebonds et fais :a passes par match.', ['p' => $pts, 'r' => $n('rebonds'), 'a' => $n('passes')]);
            if ($tirs > 0 && $tirs < 40) {
                $findings[] = __('Ta réussite aux tirs (:t %) est sous les 40 % attendus.', ['t' => $tirs]);
                $add('tir_basket', 5);
            }
            if ($lf > 0 && $lf < 65) {
                $findings[] = __('Aux lancers francs (:t %), vise au moins 70 % : ce sont des points gratuits.', ['t' => $lf]);
                $add('tir_basket', 3);
            }
            if (in_array($position, ['Pivot', 'Ailier fort'], true)) {
                if ($n('rebonds') < 6) {
                    $findings[] = 'Pour ton poste, on attend au moins 6 rebonds par match.';
                }
                $add('rebond', $n('rebonds') < 6 ? 5 : 2);
                $add('force', 2);
            } else {
                if (in_array($position, ['Meneur', 'Arrière'], true) && $n('passes') < 3) {
                    $findings[] = __('Pour un :pos, 3 passes décisives par match ou plus montrent ta vision du jeu.', ['pos' => $pos]);
                }
                $add('dribble_basket', in_array($position, ['Meneur', 'Arrière'], true) && $n('passes') < 3 ? 4 : 2);
            }
            if ($pts >= 15) {
                $good[] = 'Tu es un vrai marqueur : mets tes paniers en avant dans ta compilation.';
            }
            $add('vitesse', 2);
            $add('endurance', 1);
        } else {
            $c = max(1, $n('competitions') ?: $n('matchs'));
            $w = $n('victoires') ?: $n('buts');
            $rate = $w / $c;
            $findings[] = __('Tu as gagné ou fini sur le podium :p % du temps la saison passée.', ['p' => round($rate * 100)]);
            if ($rate < 0.4) {
                $findings[] = 'Analyse tes défaites : c’est là que tu gagneras le plus vite.';
                $add('combat', 5);
                $add('mental', 2);
            } else {
                $good[] = 'Tes résultats sont bons : il faut maintenant les montrer aux recruteurs et aux fédérations.';
                $add('combat', 3);
            }
            $add('force', 3);
            $add('vitesse', 2);
            $add('endurance', 2);
        }

        // Notes que le sportif se donne
        $qualityTheme = ['vitesse' => 'vitesse', 'endurance' => 'endurance', 'force' => 'force', 'technique' => $sport === 'Basket' ? 'dribble_basket' : ($sport === 'Football' ? 'technique' : 'combat')];
        foreach ($ratings as $q => $r) {
            if (! isset($qualityTheme[$q]) || ! $r) {
                continue;
            }
            if ($r <= 2) {
                $add($qualityTheme[$q], 3);
                $findings[] = __('Tu te sens faible en :q : on l’a ajouté à ton programme.', ['q' => mb_strtolower(__(self::QUALITIES[$q]))]);
            } elseif ($r >= 5) {
                $good[] = __(':q : tu te sens fort, garde-le en entretien.', ['q' => __(self::QUALITIES[$q])]);
            }
        }
        // Objectif choisi
        $goalTheme = [
            'Marquer plus de buts' => 'finition', 'Être titulaire' => 'endurance', 'Gagner en vitesse' => 'vitesse', 'Tenir 90 minutes' => 'endurance',
            'Mieux défendre' => 'defense', 'Préparer un essai en club' => 'vitesse', 'Revenir de blessure' => 'force', 'Progresser techniquement' => 'technique',
        ][$goal] ?? null;
        if ($goalTheme && ($sport === 'Football' || ! in_array($goalTheme, ['finition', 'defense', 'technique'], true))) {
            $add($goalTheme, 3);
        }
        if ($sport !== 'Football') {
            foreach (['finition', 'deplacements', 'passe', 'defense', 'technique', 'gardien_reflexes', 'gardien_placement', 'discipline'] as $fbOnly) {
                unset($scores[$fbOnly]);
            }
        }

        arsort($scores);
        $priorities = array_slice(array_keys($scores), 0, 3);
        while (count($priorities) < 3) {
            foreach (['vitesse', 'endurance', 'force'] as $fill) {
                if (! in_array($fill, $priorities, true) && count($priorities) < 3) {
                    $priorities[] = $fill;
                }
            }
        }
        $level = count($good) >= 2 && count($findings) <= 2 ? 'tres_bien' : (array_sum(array_slice($scores, 0, 2)) >= 9 ? 'a_travailler' : 'correct');

        // Trois séances par semaine : la priorité 1 revient deux fois
        $ex = fn (string $theme, int $count) => array_slice(self::THEMES[$theme][1], 0, $count);
        $sessions = [
            ['Séance 1', self::THEMES[$priorities[0]][0], array_merge($ex($priorities[0], 3), $ex('vitesse', 1))],
            ['Séance 2', self::THEMES[$priorities[1]][0], array_merge($ex($priorities[1], 3), $priorities[1] === 'endurance' ? [] : $ex('endurance', 1))],
            ['Séance 3', [self::THEMES[$priorities[2]][0], self::THEMES[$priorities[0]][0]], array_merge($ex($priorities[2], 2), array_slice(self::THEMES[$priorities[0]][1], -1), $priorities[2] === 'force' ? [] : [self::THEMES['force'][1][0]])],
        ];

        return [
            'sport' => $sport, 'position' => $position, 'goal' => $goal, 'level' => $level,
            'summary' => match ($level) {
                'tres_bien' => 'Belle saison ! Ton programme sert à rester à ce niveau et à le montrer aux recruteurs.',
                'a_travailler' => 'Il y a un vrai chantier, mais il est clair : en 4 semaines de travail régulier, tu peux le voir dans tes matchs.',
                default => 'Tu as une bonne base. Deux ou trois points précis te feront passer un cap.',
            },
            'findings' => $findings, 'good' => $good,
            'priorities' => array_map(fn ($t) => ['key' => $t, 'title' => self::THEMES[$t][0], 'test' => self::THEMES[$t][2]], $priorities),
            'sessions' => $sessions,
            'weeks' => [
                'Semaines 1 et 2 : fais les doses indiquées, en soignant la qualité de chaque geste.',
                'Semaines 3 et 4 : ajoute une série à chaque exercice, ou 10 % de temps en plus.',
                'Entre deux séances : un jour de repos ou de footing léger. Dors 8 heures et bois beaucoup d’eau.',
            ],
        ];
    }

    // ------------------------------------------------------------------ données

    public function lastStats(string $userKey): ?object
    {
        try {
            return DB::table('coaching_stats')->where('user_key', $userKey)->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }
    }

    public function saveStats(string $userKey, string $sport, ?string $position, array $stats, array $plan): void
    {
        DB::table('coaching_stats')->insert(['user_key' => $userKey, 'sport' => $sport, 'position' => $position,
            'stats' => json_encode($stats), 'plan' => json_encode($plan), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return list<array{0: string, 1: int, 2: int, 3: string}> */
    public static function offers(): array
    {
        $cur = Payments::currency();
        if ($cur !== 'GNF') {
            return config("terrain.prices.{$cur}.coaching") ?: ($cur === 'XOF' ? self::DEFAULT_OFFERS_XOF : self::DEFAULT_OFFERS);
        }

        return config('terrain.offers.coaching') ?: self::DEFAULT_OFFERS;
    }

    public function createRequest(string $userKey, ?string $athleteId, int $package, array $data, object $payment): object
    {
        [$label, $amount, $days] = self::offers()[$package];
        $ref = 'CO'.now()->format('ymd').Str::upper(Str::random(5));
        $id = DB::table('coaching_requests')->insertGetId([
            'reference' => $ref, 'user_key' => $userKey, 'athlete_id' => $athleteId, 'package' => $package, 'package_label' => $label,
            'days' => $days, 'amount' => $payment->amount, 'currency' => $payment->currency, 'goal' => $data['goal'] ?? null,
            'details' => $data['details'] ?? null, 'whatsapp' => $data['whatsapp'] ?? null, 'status' => 'attente_paiement',
            'payment_ref' => $payment->reference, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! empty($data['details'])) {
            $this->message($id, 'sportif', $data['details'], 'demande', $data['name'] ?? null);
        }

        return DB::table('coaching_requests')->find($id);
    }

    /** Paiement confirmé (appelé par Payments::markPaid). */
    public function paid(string $paymentRef): ?object
    {
        $r = DB::table('coaching_requests')->where('payment_ref', $paymentRef)->first();
        if (! $r) {
            return null;
        }
        DB::table('coaching_requests')->where('id', $r->id)->where('status', 'attente_paiement')->update([
            'status' => 'payee', 'paid_at' => now(), 'ends_at' => $r->days ? now()->addDays($r->days) : null, 'updated_at' => now(),
        ]);

        return DB::table('coaching_requests')->find($r->id);
    }

    public function message(int $requestId, string $author, string $body, string $kind = 'message', ?string $name = null): void
    {
        DB::table('coaching_messages')->insert(['request_id' => $requestId, 'author' => $author, 'author_name' => $name, 'kind' => $kind,
            'body' => trim($body), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('coaching_requests')->where('id', $requestId)->update(['updated_at' => now()]);
    }

    /** Coach rattaché à ce compte (un compte Frikip peut être coach). */
    public function coachFor(?string $userKey): ?object
    {
        if (! $userKey) {
            return null;
        }
        try {
            return DB::table('coaches')->where('user_key', $userKey)->where('active', true)->first();
        } catch (Throwable) {
            return null;
        }
    }
}
