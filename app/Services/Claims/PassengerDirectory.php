<?php

namespace App\Services\Claims;

use App\Models\Claim;
use App\Models\ClaimSigner;
use App\Models\Trip;
use App\Support\Passengers\PassengerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the passenger directory. There is no passengers table - a person
 * shows up as a signature roster entry, a name on a ticket, a claim's lead
 * passenger and a monitored trip - so this service is the one place that
 * decides "these records are the same human" and merges them into profiles.
 *
 * Identity: the email address when we have one (people rename, addresses
 * don't), otherwise the normalised name. Everything else in the module
 * reads profiles from here, so the merge rule lives in exactly one place.
 *
 * Scale note: the merge happens in PHP over the whole book of claims,
 * which is right while that fits comfortably in memory (thousands of
 * claims). Past that, materialise this into a passengers table kept in
 * sync by the same rules - the interface here would not change.
 */
class PassengerDirectory
{
    /** Normalised name => profile, so a later record finds the same human. */
    private array $byName = [];

    /** Personal email => profile. Only ever a signer's own address. */
    private array $byEmail = [];

    /** @return Collection<string, PassengerProfile> */
    public function all(): Collection
    {
        $this->byName = $this->byEmail = [];

        /** @var Collection<string, PassengerProfile> $people */
        $people = collect();

        $this->fromSignatureRoster($people);
        $this->fromClaimRosters($people);
        $this->fromTrips($people);

        return $people->sortBy(fn (PassengerProfile $p) => Str::lower($p->name))->values()
            ->keyBy(fn (PassengerProfile $p) => $p->key);
    }

    /**
     * The signature roster is the richest source: real names, addresses,
     * who is a guardian and which minor they cover.
     */
    private function fromSignatureRoster(Collection $people): void
    {
        $signers = ClaimSigner::with(['claim.user', 'claim.itinerary.passengers'])->get();

        foreach ($signers as $signer) {
            $person = $this->resolve($people, $signer->name, $signer->email);
            $person->addEmail($signer->email);
            $person->addRole($signer->role === ClaimSigner::ROLE_GUARDIAN
                ? PassengerProfile::ROLE_GUARDIAN
                : PassengerProfile::ROLE_PASSENGER);
            $person->signers->push($signer);
            $person->touch($signer->signed_at ?? $signer->invited_at ?? $signer->created_at);
            $this->attachClaim($person, $signer->claim);

            // A guardian's signature covers a minor who is a passenger in
            // their own right - the minor gets their own profile, linked.
            if ($signer->role === ClaimSigner::ROLE_GUARDIAN && trim((string) $signer->signs_for) !== '') {
                $person->signsFor[] = $signer->signs_for;

                $minor = $this->resolve($people, $signer->signs_for, null);
                $minor->addRole(PassengerProfile::ROLE_MINOR);
                $minor->guardian = $signer->name;
                $minor->touch($signer->signed_at ?? $signer->created_at);
                $this->attachClaim($minor, $signer->claim);
            }
        }
    }

    /**
     * Everyone named on a claim - the ticket's passenger list, or the lead
     * passenger when no roster was parsed. Catches people whose claim has
     * not reached the signature stage yet.
     */
    private function fromClaimRosters(Collection $people): void
    {
        $claims = Claim::with(['user', 'itinerary.passengers'])->get();

        foreach ($claims as $claim) {
            foreach ($claim->itinerary?->passengers ?? collect() as $passenger) {
                $person = $this->resolve($people, $passenger->full_name, null);
                $person->addRole($this->isMinorType($passenger->type)
                    ? PassengerProfile::ROLE_MINOR
                    : PassengerProfile::ROLE_PASSENGER);
                $this->attachClaim($person, $claim);
            }

            if (trim((string) $claim->passenger_name) !== '') {
                // The claim's contact address identifies the passenger only
                // on a single-passenger claim. On a family booking it is the
                // booker's, and trusting it would fuse the whole family into
                // one person. The account email is never an identity.
                $contact = count($claim->passengerNames()) <= 1 ? $claim->contact_email : null;

                $person = $this->resolve($people, $claim->passenger_name, $contact);
                $person->addRole(PassengerProfile::ROLE_PASSENGER);
                $this->attachClaim($person, $claim);
            }
        }
    }

    /** Monitored trips - people we watch for before a claim ever exists. */
    private function fromTrips(Collection $people): void
    {
        foreach (Trip::with('user')->get() as $trip) {
            $names = is_array($trip->passengers) && $trip->passengers !== []
                ? $trip->passengers
                : array_filter([$trip->passenger_name]);

            foreach ($names as $name) {
                $name = is_array($name) ? ($name['full_name'] ?? $name['name'] ?? '') : $name;

                if (trim((string) $name) === '') {
                    continue;
                }

                $person = $this->resolve($people, $name, null);
                $person->addRole(PassengerProfile::ROLE_PASSENGER);
                $person->trips->push($trip);
                $person->touch($trip->updated_at);
            }
        }
    }

    private function attachClaim(PassengerProfile $person, ?Claim $claim): void
    {
        if ($claim && !$person->claims->contains('id', $claim->id)) {
            $person->claims->push($claim);
            $person->touch($claim->updated_at);
        }
    }

    /**
     * Find this person's profile or start one.
     *
     * Address first - "T. Hagyal" and "Tenzin Hagyal" are one person when
     * they share one - then name. Only a signer's OWN address may be used
     * as an identity: the account holder's email belongs to the booker, and
     * matching on it would fuse every passenger on a family booking into a
     * single person.
     */
    private function resolve(Collection $people, ?string $name, ?string $email): PassengerProfile
    {
        $name      = trim((string) $name) ?: 'Unnamed passenger';
        $email     = Str::lower(trim((string) $email));
        $nameIndex = $this->normaliseName($name);

        if ($email !== '' && isset($this->byEmail[$email])) {
            $person = $this->byEmail[$email];
            $this->byName[$nameIndex] ??= $person;   // remember this spelling too

            return $person;
        }

        if (isset($this->byName[$nameIndex])) {
            $person = $this->byName[$nameIndex];

            if ($email !== '') {
                $person->addEmail($email);
                $this->byEmail[$email] ??= $person;
            }

            return $person;
        }

        $person = new PassengerProfile($email !== '' ? 'email:' . $email : 'name:' . $nameIndex, $name);
        $person->addEmail($email ?: null);

        $people->put($person->key, $person);
        $this->byName[$nameIndex] = $person;

        if ($email !== '') {
            $this->byEmail[$email] = $person;
        }

        return $person;
    }

    private function normaliseName(string $name): string
    {
        return Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function isMinorType(?string $type): bool
    {
        return in_array(Str::lower(trim((string) $type)), ['chd', 'child', 'inf', 'infant'], true);
    }
}
