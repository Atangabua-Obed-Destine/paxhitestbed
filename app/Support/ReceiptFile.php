<?php

namespace App\Support;

/**
 * Where a payment receipt actually is.
 *
 * Receipts belong in uploads/payment-receipts (or uploads/installment-receipts
 * for an instalment). One upload path did not follow that: the public
 * application controller stores an applicant's photograph, certificates and
 * documents in uploads/student, and reused that same directory for the
 * admission-fee receipt. The verification screen looked in
 * uploads/payment-receipts, found nothing, and the bursar got a 404 — for
 * every applicant-submitted receipt, while student-submitted ones worked.
 *
 * The upload now uses the right directory, but the receipts already taken
 * sit in the old one and their database rows record only a bare filename.
 * Rather than move files on somebody's server, this looks in the directory a
 * receipt should be in and then in the ones it is known to have been put in.
 *
 * The list is deliberately short and fixed. It is a record of a specific
 * mistake, not an invitation to search the uploads tree.
 */
class ReceiptFile
{
    /** Directories a receipt was historically written to, in order. */
    public const LEGACY_DIRECTORIES = ['student'];

    /**
     * Find a receipt.
     *
     * @return array{dir: string, relative: string, absolute: string, legacy: bool}|null
     */
    public static function locate(?string $file, ?string $preferredDirectory): ?array
    {
        $name = basename(trim((string) $file));

        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $directories = array_values(array_unique(array_filter(array_merge(
            [trim((string) $preferredDirectory, '/')],
            self::LEGACY_DIRECTORIES
        ))));

        foreach ($directories as $index => $directory) {
            $relative = 'uploads/' . $directory . '/' . $name;
            $absolute = public_path($relative);

            if (is_file($absolute)) {
                return [
                    'dir' => $directory,
                    'relative' => $relative,
                    'absolute' => $absolute,
                    // True when it was found somewhere it should not be, so
                    // the caller can say so rather than silently paper over it.
                    'legacy' => $index > 0,
                ];
            }
        }

        return null;
    }

    /** Where the file should have been, for an error message. */
    public static function expectedPath(?string $file, ?string $preferredDirectory): string
    {
        return 'uploads/' . trim((string) $preferredDirectory, '/') . '/' . basename(trim((string) $file));
    }
}
