<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminSupervisionController;
use App\Http\Controllers\AdminMediaController;
use App\Http\Controllers\AdController;
use App\Http\Controllers\CoachingController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\AdminGrowthController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GrowthController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneAuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\VideoServiceController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TerrainAuth;
use App\Http\Middleware\Throttle;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Paiement : notification serveur de CinetPay (sans jeton CSRF, sans session utilisateur)
Route::match(['get', 'post'], '/paiement/notification', [PaymentController::class, 'notify'])
    ->withoutMiddleware([ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware(Throttle::class.':120,1')->name('payments.notify');

// Diffusion Frikip (Mux) : le direct démarre et s'arrête tout seul
Route::post('/webhooks/mux', [\App\Http\Controllers\LiveController::class, 'muxWebhook'])
    ->withoutMiddleware([ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware(Throttle::class.':240,1')->name('webhooks.mux');

Route::middleware(SetLocale::class)->group(function () {

    // ---- Pages publiques (adresses à donner à Google Play et à l'App Store)
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/confidentialite', [PageController::class, 'privacy'])->name('privacy');
    Route::get('/conditions', [PageController::class, 'terms'])->name('terms');
    Route::get('/securite-enfants', [PageController::class, 'childSafety'])->name('child-safety');
    Route::get('/support', [PageController::class, 'support'])->name('support');
    Route::get('/aide-audio', [PageController::class, 'audioHelp'])->name('audio-help');
    Route::get('/langue/{locale}', [PageController::class, 'locale'])->name('locale');
    Route::get('/pays/{country}', [PageController::class, 'country'])->name('country');
    Route::get('/economie-donnees/{on}', [PageController::class, 'lite'])->whereIn('on', ['0', '1'])->name('lite');

    // ---- Suppression de compte
    Route::get('/suppression-compte', [AccountDeletionController::class, 'show'])->name('deletion');
    Route::middleware(Throttle::class.':6,1')->group(function () {
        Route::post('/suppression-compte/connexion', [AccountDeletionController::class, 'login'])->name('deletion.login');
        Route::post('/suppression-compte/confirmer', [AccountDeletionController::class, 'confirm'])->name('deletion.confirm');
        Route::post('/suppression-compte/demande', [AccountDeletionController::class, 'storeRequest'])->name('deletion.request');
    });
    Route::post('/suppression-compte/annuler', [AccountDeletionController::class, 'cancel'])->name('deletion.cancel');

    // ---- Comptes
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware(Throttle::class.':10,1');
    Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->middleware(Throttle::class.':10,1');
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
    // Connexion / inscription par numéro de téléphone + code SMS
    Route::get('/connexion/telephone', [PhoneAuthController::class, 'show'])->name('phone.login');
    Route::post('/connexion/telephone', [PhoneAuthController::class, 'send'])->middleware(Throttle::class.':5,10')->name('phone.send');
    Route::post('/connexion/telephone/code', [PhoneAuthController::class, 'verify'])->middleware(Throttle::class.':10,10')->name('phone.verify');
    Route::post('/connexion/telephone/mot-de-passe', [PhoneAuthController::class, 'password'])->middleware(Throttle::class.':10,10')->name('phone.password');
    Route::get('/connexion/telephone/profil', [PhoneAuthController::class, 'profile'])->name('phone.profile');
    Route::post('/connexion/telephone/profil', [PhoneAuthController::class, 'complete'])->middleware(Throttle::class.':10,1');
    // Vrais comptes : mot de passe oublié
    Route::get('/mot-de-passe-oublie', [AccountController::class, 'forgot'])->name('password.forgot');
    Route::post('/mot-de-passe-oublie', [AccountController::class, 'sendReset'])->middleware(Throttle::class.':5,10');
    Route::get('/mot-de-passe/{token}', [AccountController::class, 'resetForm'])->name('password.reset');
    Route::post('/mot-de-passe/{token}', [AccountController::class, 'reset'])->middleware(Throttle::class.':10,10');
    // Parrainage : lien d'invitation
    Route::get('/i/{code}', [GrowthController::class, 'invite'])->name('referral.link');

    // ---- Visibles sans compte
    Route::get('/fil', [FeedController::class, 'index'])->name('feed');
    Route::get('/fil/suite', [FeedController::class, 'more'])->name('feed.more');
    Route::get('/videos/{video}/commentaires', [FeedController::class, 'comments'])->name('videos.comments');
    Route::post('/videos/{video}/vue', [GrowthController::class, 'videoView'])->middleware(Throttle::class.':120,1')->name('videos.view');
    Route::post('/videos/{video}/partage', [FeedController::class, 'share'])->middleware(Throttle::class.':30,1')->name('videos.share');
    Route::get('/videos/{video}/sous-titres/{lang}.vtt', [VideoController::class, 'subtitles'])->whereIn('lang', ['fr', 'en'])->name('videos.subtitles');
    Route::get('/matchs', [MatchController::class, 'index'])->name('matches');
    Route::get('/matchs/fragment', [MatchController::class, 'listPartial'])->name('matches.partial');
    Route::get('/matchs/direct', [MatchController::class, 'liveTicker'])->name('matches.ticker');
    Route::post('/mon-score', [MatchController::class, 'chooseScore'])->middleware(Throttle::class.':30,1')->name('score.choose');
    Route::get('/api/matchs', [MatchController::class, 'api'])->middleware(Throttle::class.':60,1')->name('api.matches');
    Route::get('/matchs/{id}', [MatchController::class, 'show'])->name('matches.show');
    Route::get('/matchs/{id}/fragment', [MatchController::class, 'detailPartial'])->name('matches.show.partial');
    Route::get('/sportifs/{id}', [AthleteController::class, 'show'])->name('athletes.show');
    Route::get('/sportifs/{id}/carte', [GrowthController::class, 'card'])->name('athletes.card');
    Route::get('/sportifs/{id}/carte.png', [GrowthController::class, 'cardImage'])->name('athletes.card.png');
    Route::get('/cv/{id}', [GrowthController::class, 'cv'])->name('athletes.cv');
    Route::get('/j/{id}', [GrowthController::class, 'short'])->name('athletes.short');
    Route::view('/explorer', 'web.explore')->name('explore');
    Route::get('/actu', [PostController::class, 'index'])->name('posts');
    Route::get('/actu/{id}', [PostController::class, 'show'])->whereNumber('id')->name('posts.show');
    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs');
    Route::get('/clubs/{slug}', [ClubController::class, 'show'])->name('clubs.show');
    Route::get('/detections', [EventController::class, 'index'])->name('events');
    Route::get('/detections/{id}', [EventController::class, 'show'])->whereNumber('id')->name('events.show');
    Route::get('/defis', [ChallengeController::class, 'index'])->name('challenges');
    Route::get('/defis/{id}', [ChallengeController::class, 'show'])->whereNumber('id')->name('challenges.show');
    Route::get('/classements', [GrowthController::class, 'rankings'])->name('rankings');
    Route::get('/ils-ont-ete-recrutes', [GrowthController::class, 'stories'])->name('stories');
    Route::get('/partenaires', [GrowthController::class, 'partners'])->name('partners');
    Route::get('/structure-pro', [ClubController::class, 'offer'])->name('structure.offer');
    Route::get('/services-video', [VideoServiceController::class, 'index'])->name('video.services');
    // Coaching, matchs en direct, publicités
    Route::get('/coaching', [CoachingController::class, 'index'])->name('coaching');
    Route::post('/coaching/programme', [CoachingController::class, 'plan'])->middleware(Throttle::class.':20,1')->name('coaching.plan');
    Route::get('/coaching/mon-programme', [CoachingController::class, 'showPlan'])->name('coaching.plan.show');
    Route::get('/direct', [LiveController::class, 'index'])->name('live');
    Route::get('/direct/tous-les-matchs', [LiveController::class, 'partner'])->name('live.partner');
    Route::get('/direct/{id}', [LiveController::class, 'show'])->whereNumber('id')->name('live.show');
    Route::get('/regarder/{id}', [LiveController::class, 'watch'])->whereNumber('id')->middleware(Throttle::class.':60,1')->name('watch');
    Route::get('/direct/{id}/etat', [LiveController::class, 'state'])->whereNumber('id')->middleware(Throttle::class.':60,1')->name('live.state');
    Route::post('/pub/{id}/vue', [AdController::class, 'seen'])->whereNumber('id')->middleware(Throttle::class.':60,1')->name('ads.seen');
    Route::get('/pub/{id}/clic', [AdController::class, 'click'])->whereNumber('id')->middleware(Throttle::class.':30,1')->name('ads.click');
    Route::get('/pub/{id}/video', [AdController::class, 'video'])->whereNumber('id')->name('ads.video');
    Route::get('/pub/{id}/affiche', [AdController::class, 'poster'])->whereNumber('id')->name('ads.poster');
    Route::post('/partenaires', [GrowthController::class, 'storePartner'])->middleware(Throttle::class.':5,10')->name('partners.store');

    // ---- Réservé aux utilisateurs connectés
    Route::middleware(TerrainAuth::class)->group(function () {
        Route::post('/videos/{video}/aime', [FeedController::class, 'like'])->middleware(Throttle::class.':60,1')->name('videos.like');
        Route::post('/videos/{video}/commentaires', [FeedController::class, 'comment'])->middleware(Throttle::class.':10,1')->name('videos.comment');
        Route::post('/commentaires/{comment}/aime', [FeedController::class, 'likeComment'])->middleware(Throttle::class.':60,1')->name('comments.like');
        Route::post('/commentaires/{comment}/supprimer', [FeedController::class, 'deleteComment'])->middleware(Throttle::class.':30,1')->name('comments.delete');
        Route::post('/commentaires/{comment}/epingler', [FeedController::class, 'pinComment'])->middleware(Throttle::class.':30,1')->name('comments.pin');
        Route::get('/videos/{video}/langue', [VideoController::class, 'edit'])->name('videos.edit');
        Route::post('/videos/{video}/langue', [VideoController::class, 'update'])->middleware(Throttle::class.':20,1');
        Route::post('/sportifs/{athlete}/suivre', [FeedController::class, 'follow'])->name('athletes.follow');
        Route::post('/matchs/{id}/vote', [MatchController::class, 'vote'])->name('matches.vote');
        Route::get('/recherche', [AthleteController::class, 'search'])->name('search');
        Route::post('/actu', [PostController::class, 'store'])->middleware(Throttle::class.':10,1')->name('posts.store');
        Route::post('/actu/{id}/aime', [PostController::class, 'like'])->whereNumber('id')->middleware(Throttle::class.':60,1')->name('posts.like');
        Route::post('/actu/{id}/commentaires', [PostController::class, 'comment'])->whereNumber('id')->middleware(Throttle::class.':10,1')->name('posts.comment');
        Route::post('/actu/{id}/supprimer', [PostController::class, 'destroy'])->whereNumber('id')->name('posts.destroy');
        Route::post('/stories', [\App\Http\Controllers\StoryController::class, 'store'])->middleware(Throttle::class.':10,1')->name('fkstories.store');
        Route::post('/stories/{id}/vue', [\App\Http\Controllers\StoryController::class, 'view'])->whereNumber('id')->middleware(Throttle::class.':120,1')->name('fkstories.view');
        Route::post('/stories/{id}/supprimer', [\App\Http\Controllers\StoryController::class, 'destroy'])->whereNumber('id')->name('fkstories.destroy');
        Route::post('/stories/{id}/signaler', [\App\Http\Controllers\StoryController::class, 'report'])->whereNumber('id')->middleware(Throttle::class.':10,1')->name('fkstories.report');
        Route::post('/actu/{id}/signaler', [PostController::class, 'report'])->whereNumber('id')->middleware(Throttle::class.':10,1')->name('posts.report');
        Route::get('/comparer', [AthleteController::class, 'compare'])->name('compare');
        Route::get('/offres', [OfferController::class, 'index'])->name('offers');
        Route::get('/messages', [MessageController::class, 'index'])->name('messages');
        Route::get('/messages/{partner}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{partner}', [MessageController::class, 'store'])->middleware(Throttle::class.':30,1')->name('messages.store');
        Route::post('/messages/{partner}/fichier', [MessageController::class, 'upload'])->middleware(Throttle::class.':20,1')->name('messages.upload');
        Route::get('/messages/{partner}/nouveaux', [MessageController::class, 'poll'])->middleware(Throttle::class.':120,1')->name('messages.poll');
        Route::get('/fichiers/messages/{message}', [MessageController::class, 'media'])->name('messages.media');

        // Appels audio et vidéo
        Route::post('/appeler/{partner}', [CallController::class, 'start'])->middleware(Throttle::class.':12,1')->name('calls.start');
        Route::get('/appels/entrant', [CallController::class, 'incoming'])->middleware(Throttle::class.':60,1')->name('calls.incoming');
        Route::get('/appels/{call}', [CallController::class, 'show'])->name('calls.show');
        Route::get('/appels/{call}/etat', [CallController::class, 'state'])->middleware(Throttle::class.':200,1')->name('calls.state');
        Route::post('/appels/{call}/signal', [CallController::class, 'signal'])->middleware(Throttle::class.':300,1')->name('calls.signal');
        Route::post('/appels/{call}/statut', [CallController::class, 'status'])->middleware(Throttle::class.':60,1')->name('calls.status');

        // Profil, mot de passe, notifications du téléphone, historique des paiements
        Route::get('/profil/modifier', [AccountController::class, 'edit'])->name('profile.edit');
        Route::post('/profil/modifier', [AccountController::class, 'update'])->middleware(Throttle::class.':20,1');
        Route::post('/profil/mot-de-passe', [AccountController::class, 'password'])->middleware(Throttle::class.':10,10')->name('profile.password');
        Route::post('/push/abonnement', [PushController::class, 'subscribe'])->middleware(Throttle::class.':20,1')->name('push.subscribe');
        Route::post('/push/desabonnement', [PushController::class, 'unsubscribe'])->middleware(Throttle::class.':20,1')->name('push.unsubscribe');
        Route::post('/push/essai', [PushController::class, 'test'])->middleware(Throttle::class.':5,1')->name('push.test');
        Route::get('/mes-paiements', [PaymentController::class, 'history'])->name('payments.history');
        // Envoi des vidéos par morceaux (publication et commandes de montage)
        Route::post('/publier/morceau', [PublishController::class, 'chunk'])->middleware(Throttle::class.':400,1')->name('publish.chunk');
        // Services vidéo : commande de montage
        Route::get('/services-video/commander/{package}', [VideoServiceController::class, 'create'])->whereNumber('package')->name('video.order');
        Route::post('/services-video/commander', [VideoServiceController::class, 'store'])->middleware(Throttle::class.':10,1')->name('video.order.store');
        Route::get('/mes-commandes', [VideoServiceController::class, 'orders'])->name('video.orders');
        Route::post('/mes-commandes/{ref}/payer', [VideoServiceController::class, 'pay'])->middleware(Throttle::class.':10,1')->name('video.order.pay');

        // Compte, notifications, statistiques
        Route::get('/moi', [MeController::class, 'show'])->name('me');
        Route::get('/moi/statistiques', [GrowthController::class, 'stats'])->name('stats');
        Route::get('/parametres', [MeController::class, 'settings'])->name('settings');
        Route::post('/parametres/preferences', [GrowthController::class, 'prefs'])->name('prefs');
        Route::get('/notifications', [GrowthController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/lues', [GrowthController::class, 'readNotifications'])->name('notifications.read');
        Route::post('/signaler', [MeController::class, 'report'])->middleware(Throttle::class.':10,1')->name('report');
        Route::post('/parametres/supprimer-compte', [MeController::class, 'destroy'])->middleware(Throttle::class.':5,1')->name('account.destroy');
        Route::get('/verification', [VerificationController::class, 'show'])->name('verification');
        Route::post('/verification', [VerificationController::class, 'store'])->middleware(Throttle::class.':5,10');
        Route::get('/parrainage', [GrowthController::class, 'referral'])->name('referral');
        Route::post('/ils-ont-ete-recrutes', [GrowthController::class, 'storeStory'])->middleware(Throttle::class.':3,10')->name('stories.store');

        // Paiement Mobile Money
        Route::get('/paiement/{product}', [PaymentController::class, 'show'])->whereIn('product', ['premium', 'boost', 'structure', 'sans_pub'])->name('premium');
        Route::post('/paiement/{product}', [PaymentController::class, 'start'])->whereIn('product', ['premium', 'boost', 'structure', 'sans_pub'])->middleware(Throttle::class.':10,1');
        Route::get('/paiement/simulation/{ref}', [PaymentController::class, 'simulation'])->name('payments.simulation');
        Route::post('/paiement/simulation/{ref}', [PaymentController::class, 'confirmSimulation'])->middleware(Throttle::class.':10,1');
        Route::get('/paiement/retour/{ref}', [PaymentController::class, 'back'])->name('payments.return');

        // Coaching payant : réservation, suivi, espace coach
        Route::get('/coaching/reserver/{package}', [CoachingController::class, 'reserve'])->whereNumber('package')->name('coaching.reserve');
        Route::post('/coaching/reserver', [CoachingController::class, 'store'])->middleware(Throttle::class.':10,1')->name('coaching.store');
        Route::post('/coaching/reserver/{ref}/payer', [CoachingController::class, 'pay'])->middleware(Throttle::class.':10,1')->name('coaching.pay');
        Route::get('/coaching/suivi', [CoachingController::class, 'follow'])->name('coaching.follow');
        Route::get('/coaching/suivi/{ref}', [CoachingController::class, 'thread'])->name('coaching.thread');
        Route::post('/coaching/suivi/{ref}', [CoachingController::class, 'send'])->middleware(Throttle::class.':20,1')->name('coaching.send');

        // Défis, détections, billets
        Route::post('/defis/vote/{entry}', [ChallengeController::class, 'vote'])->middleware(Throttle::class.':30,1')->name('challenges.vote');
        Route::get('/billets/{code}', [EventController::class, 'ticket'])->name('tickets.show');
        Route::post('/billets/{code}/pointer', [EventController::class, 'checkIn'])->name('tickets.checkin');
    });
    Route::middleware(TerrainAuth::class.':athlete')->group(function () {
        Route::get('/publier', [PublishController::class, 'create'])->name('publish');
        Route::post('/publier', [PublishController::class, 'store'])->middleware(Throttle::class.':10,1');
        Route::post('/offres/{offer}/postuler', [OfferController::class, 'apply'])->name('offers.apply');
        Route::post('/defis/participer', [ChallengeController::class, 'enter'])->name('challenges.enter');
        Route::post('/detections/{id}/inscription', [EventController::class, 'register'])->whereNumber('id')->name('events.register');
    });
    Route::middleware(TerrainAuth::class.':recruiter')->group(function () {
        Route::post('/offres', [OfferController::class, 'store'])->name('offers.store');
        Route::post('/matchs/{id}/organisateur', [MatchController::class, 'organizer'])->name('matches.organizer');
        Route::get('/moi/club', [ClubController::class, 'edit'])->name('clubs.edit');
        Route::post('/moi/club', [ClubController::class, 'update']);
        Route::post('/detections', [EventController::class, 'store'])->name('events.store');
        Route::get('/detections/{id}/inscrits', [EventController::class, 'manage'])->whereNumber('id')->name('events.manage');
        Route::get('/direct/diffuser', [LiveController::class, 'create'])->name('live.create');
        Route::post('/direct/diffuser', [LiveController::class, 'store'])->middleware(Throttle::class.':10,10')->name('live.store');
        Route::get('/direct/{id}/gerer', [LiveController::class, 'manage'])->whereNumber('id')->name('live.manage');
        Route::post('/direct/{id}/gerer', [LiveController::class, 'update'])->whereNumber('id')->middleware(Throttle::class.':120,1')->name('live.update');
        Route::get('/alertes', [AlertController::class, 'index'])->name('alerts');
        Route::post('/alertes', [AlertController::class, 'store'])->name('alerts.store');
        Route::post('/alertes/{id}/supprimer', [AlertController::class, 'destroy'])->name('alerts.destroy');
    });

    // ---- Anciennes adresses en .html (si elles ont déjà été données à un store)
    foreach (['confidentialite', 'conditions', 'securite-enfants', 'support', 'suppression-compte'] as $page) {
        Route::redirect("/{$page}.html", "/{$page}", 301);
    }
    Route::redirect('/index.html', '/', 301);

    // ---- Espace admin
    Route::get('/admin/connexion', [AdminController::class, 'loginForm'])->name('admin.login');
    Route::post('/admin/connexion', [AdminController::class, 'login'])->middleware(Throttle::class.':5,1');
    Route::get('/admin/installation', [AdminController::class, 'setupForm'])->name('admin.setup');
    Route::post('/admin/installation', [AdminController::class, 'setup'])->middleware(Throttle::class.':5,1');
    Route::middleware(AdminOnly::class)->prefix('admin')->name('admin.')->group(function () {
        // Supervision : vue d'ensemble, membres, modération, algorithme, journal
        Route::get('/', [AdminSupervisionController::class, 'overview'])->name('index');
        Route::get('/membres', [AdminSupervisionController::class, 'members'])->name('members');
        Route::get('/membres/{key}', [AdminSupervisionController::class, 'member'])->name('member');
        Route::post('/membres/{key}', [AdminSupervisionController::class, 'memberAction'])->name('member.action');
        Route::get('/moderation', [AdminSupervisionController::class, 'moderation'])->name('moderation');
        Route::post('/moderation/evenements/{id}', [AdminSupervisionController::class, 'decide'])->whereNumber('id')->name('moderation.decide');
        Route::get('/algorithme', [AdminSupervisionController::class, 'algorithm'])->name('algorithm');
        Route::post('/algorithme', [AdminSupervisionController::class, 'saveAlgorithm'])->name('algorithm.save');
        Route::get('/journal', [AdminSupervisionController::class, 'journal'])->name('journal');
        // Comptes admin, mon compte, gestion des membres et des contenus
        Route::get('/admins', [\App\Http\Controllers\AdminManageController::class, 'admins'])->name('admins');
        Route::post('/admins', [\App\Http\Controllers\AdminManageController::class, 'saveAdmin'])->name('admins.save');
        Route::post('/admins/{id}', [\App\Http\Controllers\AdminManageController::class, 'adminAction'])->whereNumber('id')->name('admins.action');
        Route::get('/mon-compte', [\App\Http\Controllers\AdminManageController::class, 'account'])->name('account');
        Route::post('/mon-compte', [\App\Http\Controllers\AdminManageController::class, 'saveAccount'])->name('account.save');
        Route::post('/membres/{key}/gerer', [\App\Http\Controllers\AdminManageController::class, 'memberManage'])->name('member.manage');
        Route::post('/videos/{id}/supprimer', [\App\Http\Controllers\AdminManageController::class, 'deleteVideo'])->name('videos.delete');
        Route::get('/contenus', [\App\Http\Controllers\AdminManageController::class, 'content'])->name('content');
        Route::get('/actu', [\App\Http\Controllers\AdminActuController::class, 'index'])->name('actu');
        Route::post('/actu/publications/{id}', [\App\Http\Controllers\AdminActuController::class, 'post'])->whereNumber('id')->name('actu.post');
        Route::post('/actu/commentaires/{id}/supprimer', [\App\Http\Controllers\AdminActuController::class, 'deleteComment'])->whereNumber('id')->name('actu.comment');
        Route::post('/actu/stories/{id}', [\App\Http\Controllers\AdminActuController::class, 'story'])->whereNumber('id')->name('actu.story');
        Route::post('/contenus/{type}/{id}/supprimer', [\App\Http\Controllers\AdminManageController::class, 'deleteContent'])->whereIn('type', ['offre', 'detection', 'commentaire', 'club'])->name('content.delete');
        Route::post('/paiements/{id}', [\App\Http\Controllers\AdminManageController::class, 'paymentAction'])->whereNumber('id')->name('payments.action');
        Route::get('/publicites', [AdminMediaController::class, 'ads'])->name('ads');
        Route::post('/publicites', [AdminMediaController::class, 'saveAd'])->name('ads.save');
        Route::post('/publicites/morceau', [AdminMediaController::class, 'adChunk'])->name('ads.chunk');
        Route::post('/publicites/reglages', [AdminMediaController::class, 'saveSettings'])->name('media.settings');
        Route::post('/publicites/{id}', [AdminMediaController::class, 'adAction'])->whereNumber('id')->name('ads.action');
        Route::get('/direct', [AdminMediaController::class, 'live'])->name('live');
        Route::post('/direct', [AdminMediaController::class, 'saveLive'])->name('live.save');
        Route::post('/direct/rechercher', [AdminMediaController::class, 'syncLive'])->name('live.sync');
        Route::post('/direct/{id}', [AdminMediaController::class, 'liveAction'])->whereNumber('id')->name('live.action');
        Route::get('/diffuseurs', [AdminMediaController::class, 'broadcasters'])->name('broadcasters');
        Route::post('/diffuseurs', [AdminMediaController::class, 'saveBroadcaster'])->name('broadcasters.save');
        Route::post('/diffuseurs/{id}', [AdminMediaController::class, 'broadcasterAction'])->whereNumber('id')->name('broadcasters.action');
        Route::get('/coaching', [AdminMediaController::class, 'coaching'])->name('coaching');
        Route::post('/coaching/coachs', [AdminMediaController::class, 'saveCoach'])->name('coaching.coach');
        Route::get('/coaching/{id}', [AdminMediaController::class, 'coachingRequest'])->whereNumber('id')->name('coaching.request');
        Route::post('/coaching/{id}', [AdminMediaController::class, 'coachingUpdate'])->whereNumber('id')->name('coaching.update');
        Route::get('/suppressions', [AdminController::class, 'index'])->name('deletions');
        Route::post('/demandes/{deletionRequest}', [AdminController::class, 'update'])->name('update');
        Route::post('/deconnexion', [AdminController::class, 'logout'])->name('logout');
        Route::get('/signalements', [AdminGrowthController::class, 'reports'])->name('reports');
        Route::post('/signalements/{id}', [AdminGrowthController::class, 'handleReport'])->name('reports.handle');
        Route::post('/moderation/decision', [AdminGrowthController::class, 'moderate'])->name('moderate');
        Route::post('/moderation/{id}/lever', [AdminGrowthController::class, 'lift'])->name('lift');
        Route::get('/verifications', [AdminGrowthController::class, 'verifications'])->name('verifications');
        Route::post('/verifications/{id}', [AdminGrowthController::class, 'decideVerification'])->name('verifications.decide');
        Route::get('/verifications/{id}/{which}', [AdminGrowthController::class, 'file'])->whereIn('which', ['piece', 'tuteur'])->name('file');
        Route::get('/temoignages', [AdminGrowthController::class, 'stories'])->name('stories');
        Route::post('/temoignages/{id}', [AdminGrowthController::class, 'decideStory'])->name('stories.decide');
        Route::get('/partenaires', [AdminGrowthController::class, 'partners'])->name('partners');
        Route::post('/partenaires/{id}', [AdminGrowthController::class, 'decidePartner'])->name('partners.decide');
        Route::get('/paiements', [AdminGrowthController::class, 'payments'])->name('payments');
        Route::get('/montages', [AdminGrowthController::class, 'montages'])->name('montages');
        Route::get('/montages/{id}/fichier/{n}', [AdminGrowthController::class, 'montageFile'])->whereNumber(['id', 'n'])->name('montages.file');
        Route::post('/montages/{id}', [AdminGrowthController::class, 'updateMontage'])->whereNumber('id')->name('montages.update');
        Route::get('/clubs', [AdminGrowthController::class, 'clubs'])->name('clubs');
        Route::post('/clubs/{id}', [AdminGrowthController::class, 'verifyClub'])->name('clubs.verify');
        Route::get('/defis', [AdminGrowthController::class, 'challenges'])->name('challenges');
        Route::post('/defis', [AdminGrowthController::class, 'createChallenge'])->name('challenges.create');
        Route::post('/defis/{id}/cloturer', [AdminGrowthController::class, 'closeChallenge'])->name('challenges.close');
        Route::post('/defis/participation/{id}', [AdminGrowthController::class, 'excludeEntry'])->whereNumber('id')->name('challenges.exclude');
    });
});
