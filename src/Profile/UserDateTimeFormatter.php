<?php

namespace App\Profile;

use App\Entity\User;

final class UserDateTimeFormatter
{
    public function formatDate(?\DateTimeInterface $value, User $user): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->asUserDateTime($value, $user)->format('d/m/Y');
    }

    public function formatDateTime(?\DateTimeInterface $value, User $user): ?string
    {
        if (null === $value) {
            return null;
        }

        return $this->asUserDateTime($value, $user)->format('d/m/Y H:i');
    }

    private function asUserDateTime(\DateTimeInterface $value, User $user): \DateTimeImmutable
    {
        $timezone = $this->resolveUserTimezone($user);

        return \DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
    }

    private function resolveUserTimezone(User $user): \DateTimeZone
    {
        $timezone = $user->getPreferredTimezone();
        if (is_string($timezone) && User::isSupportedTimezone($timezone)) {
            return new \DateTimeZone($timezone);
        }

        return new \DateTimeZone(date_default_timezone_get());
    }
}
