<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

final class SensitiveDataProcessor
{
    private const MASK = '[MASQUE]';

    private const SENSITIVE_KEY_PATTERN = '/(?:^|_)(?:authorization|cookie|dsn|email|jeton|mot_de_passe|password|session|token)(?:$|_)/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->sanitizeString($record->message),
            context: $this->sanitizeArray($record->context),
            extra: $this->sanitizeArray($record->extra),
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $values[$key] = self::MASK;
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $values[$key] = $this->sanitizeString($value);
            }
        }

        return $values;
    }

    private function sanitizeString(string $value): string
    {
        return preg_replace(
            [
                '#(/premiere-connexion/)[^/\\s?&\\#"\']+(?=[/\\s?&\\#"\']|$)#i',
                '#([?&][A-Za-z0-9_.%~-]*(?:token|jeton|email|password|mot_de_passe|session)[A-Za-z0-9_.%~-]*)=([^&\\s"\'<>]+)#i',
                '#\\b(?:Authorization|Proxy-Authorization):\\s*[^\\r\\n]+#i',
                '#\\bCookie:\\s*[^\\r\\n]+#i',
                '#\\b(?:Bearer|Basic)\\s+[A-Za-z0-9._~+/=-]+#i',
                '#((?:postgres(?:ql)?|mysql|smtp|smtps)://[^:/\\s]+:)[^@\\s]+@#i',
                '#\\b(?:PHPSESSID|session)=([^;\\s]+)#i',
                '#[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}#i',
            ],
            [
                '$1'.self::MASK,
                '$1='.self::MASK,
                'Authorization: '.self::MASK,
                'Cookie: '.self::MASK,
                self::MASK,
                '$1'.self::MASK.'@',
                'session='.self::MASK,
                self::MASK,
            ],
            $value,
        ) ?? self::MASK;
    }
}
