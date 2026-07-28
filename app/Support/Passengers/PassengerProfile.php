<?php

namespace App\Support\Passengers;

use App\Models\ClaimSigner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One human, assembled from everywhere they appear: the signature roster,
 * the ticket's passenger list, the claims they are on and the trips being
 * monitored for them. Passengers are not a table - they are people spread
 * across the booking data, so this is the shape the admin gets to work with.
 */
final class PassengerProfile
{
    public const ROLE_PASSENGER = 'passenger';
    public const ROLE_GUARDIAN  = 'guardian';
    public const ROLE_MINOR     = 'minor';

    /** @var array<string, true> */
    public array $roles = [];

    /** @var Collection<int, \App\Models\Claim> */
    public Collection $claims;

    /** @var Collection<int, \App\Models\Trip> */
    public Collection $trips;

    /** @var Collection<int, ClaimSigner> Signatures this person is responsible for. */
    public Collection $signers;

    /** Emails seen for this person, best first. */
    public array $emails = [];

    /** For a minor: the guardian who signs on their behalf. */
    public ?string $guardian = null;

    /** Minors this person signs for. */
    public array $signsFor = [];

    public ?Carbon $lastActivity = null;

    public function __construct(
        public readonly string $key,
        public string $name,
    ) {
        $this->claims  = collect();
        $this->trips   = collect();
        $this->signers = collect();
    }

    public function addRole(string $role): void
    {
        $this->roles[$role] = true;
    }

    public function is(string $role): bool
    {
        return isset($this->roles[$role]);
    }

    public function addEmail(?string $email): void
    {
        $email = trim((string) $email);

        if ($email !== '' && !in_array($email, $this->emails, true)) {
            $this->emails[] = $email;
        }
    }

    public function email(): ?string
    {
        return $this->emails[0] ?? null;
    }

    public function touch(?Carbon $moment): void
    {
        if ($moment && (!$this->lastActivity || $moment->gt($this->lastActivity))) {
            $this->lastActivity = $moment;
        }
    }

    /** @return Collection<int, ClaimSigner> */
    public function pendingSignatures(): Collection
    {
        return $this->signers->where('status', ClaimSigner::STATUS_PENDING);
    }

    public function hasPendingSignature(): bool
    {
        return $this->pendingSignatures()->isNotEmpty();
    }

    /** A signer who cannot be chased because we have no address for them. */
    public function isUnreachable(): bool
    {
        return $this->email() === null && $this->hasPendingSignature();
    }

    /** Compensation across this person's claims, per currency. */
    public function compensation(): array
    {
        return $this->claims
            ->filter(fn ($claim) => (float) $claim->compensation_amount > 0)
            ->groupBy(fn ($claim) => $claim->compensation_currency ?: 'CAD')
            ->map(fn ($claims) => $claims->sum(fn ($claim) => (float) $claim->compensation_amount))
            ->all();
    }

    public function roleLabel(): string
    {
        return match (true) {
            $this->is(self::ROLE_MINOR)    => 'Minor',
            $this->is(self::ROLE_GUARDIAN) => 'Guardian',
            default                        => 'Passenger',
        };
    }
}
