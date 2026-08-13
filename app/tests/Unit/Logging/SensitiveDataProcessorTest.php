<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\SensitiveDataProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class SensitiveDataProcessorTest extends TestCase
{
    public function testIlMasqueLaRouteEtLesParametresSensibles(): void
    {
        $jeton = 'jeton-premiere-connexion-ultra-secret';
        $processeur = new SensitiveDataProcessor();

        $enregistrement = $processeur(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'request',
            level: Level::Error,
            message: sprintf(
                'GET /premiere-connexion/%s?email=personne@example.org&retour=public&session_id=session-secrete',
                $jeton,
            ),
            context: ['request_uri' => '/connexion?access_token=secret&retour=%2F'],
        ));

        self::assertStringNotContainsString($jeton, $enregistrement->message);
        self::assertStringNotContainsString('personne@example.org', $enregistrement->message);
        self::assertStringNotContainsString('session-secrete', $enregistrement->message);
        self::assertStringContainsString('retour=public', $enregistrement->message);
        self::assertSame('/connexion?access_token=[MASQUE]&retour=%2F', $enregistrement->context['request_uri']);
    }

    public function testIlMasqueLesClesEnTetesCookiesEtMotsDePasseAChaqueNiveau(): void
    {
        $processeur = new SensitiveDataProcessor();
        $enregistrement = $processeur(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Warning,
            message: "Authorization: Bearer jeton-secret\nCookie: PHPSESSID=cookie-secret; consentement=oui",
            context: [
                'route_parameters' => ['jeton' => 'valeur-secrete', 'id' => 'identifiant-public'],
                'headers' => [
                    'Authorization' => 'Bearer autre-secret',
                    'Cookie' => 'PHPSESSID=encore-secret',
                    'Accept' => 'text/html',
                ],
                'dsn' => 'postgresql://benevole_jambville_app:mot-de-passe-secret@database/benevole_jambville',
                'formulaire' => ['mot_de_passe' => 'phrase-secrete'],
            ],
            extra: ['email_utilisateur' => 'benevole@example.org'],
        ));

        self::assertStringNotContainsString('jeton-secret', $enregistrement->message);
        self::assertStringNotContainsString('cookie-secret', $enregistrement->message);
        self::assertSame('[MASQUE]', $enregistrement->context['route_parameters']['jeton']);
        self::assertSame('identifiant-public', $enregistrement->context['route_parameters']['id']);
        self::assertSame('[MASQUE]', $enregistrement->context['headers']['Authorization']);
        self::assertSame('[MASQUE]', $enregistrement->context['headers']['Cookie']);
        self::assertSame('text/html', $enregistrement->context['headers']['Accept']);
        self::assertSame('[MASQUE]', $enregistrement->context['dsn']);
        self::assertSame('[MASQUE]', $enregistrement->context['formulaire']['mot_de_passe']);
        self::assertSame('[MASQUE]', $enregistrement->extra['email_utilisateur']);
    }

    public function testIlMasqueUnMotDePasseDansUnDsnSansCleSensible(): void
    {
        $processeur = new SensitiveDataProcessor();
        $enregistrement = $processeur(new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'doctrine',
            level: Level::Error,
            message: 'Connexion impossible à postgresql://benevole_jambville_app:secret-dsn@database/benevole_jambville',
        ));

        self::assertStringNotContainsString('secret-dsn', $enregistrement->message);
        self::assertStringContainsString('postgresql://benevole_jambville_app:[MASQUE]@database/benevole_jambville', $enregistrement->message);
    }
}
