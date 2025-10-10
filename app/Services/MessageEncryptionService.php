<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class MessageEncryptionService
{
    /**
     * Encrypt a message before storing in database.
     *
     * @param string $message
     * @return string
     */
    public function encryptMessage(string $message): string
    {
        return Crypt::encryptString($message);
    }

    /**
     * Decrypt a message from database.
     * Note: This is provided for completeness, but frontend will handle decryption.
     *
     * @param string $encryptedMessage
     * @return string
     */
    public function decryptMessage(string $encryptedMessage): string
    {
        return Crypt::decryptString($encryptedMessage);
    }

    /**
     * Check if a string is encrypted (starts with base64 encoded Laravel encryption prefix).
     *
     * @param string $message
     * @return bool
     */
    public function isEncrypted(string $message): bool
    {
        // Laravel encrypted strings start with base64 encoded "eyJpdiI6" (which is {"iv":)
        return str_starts_with($message, 'eyJpdiI6');
    }

    /**
     * Encrypt message only if it's not already encrypted.
     *
     * @param string $message
     * @return string
     */
    public function encryptIfNeeded(string $message): string
    {
        if ($this->isEncrypted($message)) {
            return $message;
        }

        return $this->encryptMessage($message);
    }
}
