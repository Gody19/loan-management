<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberNextOfKin;
use App\Models\MemberStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class MemberService
{
    public function __construct(
        private MemberNumberGenerator $numberGenerator,
        private AuditService $auditService,
    ) {}

    public function getForUser($query, $user)
    {
        if ($user->hasRole('Super Administrator')) {
            return $query;
        }

        if ($user->hasRole('Organization Administrator')) {
            $orgIds = $user->organizations()->pluck('organizations.id');

            return $query->whereIn('organization_id', $orgIds);
        }

        if ($user->hasRole('Branch Manager')) {
            $branchIds = $user->branches()->pluck('branches.id');

            return $query->whereIn('branch_id', $branchIds);
        }

        $orgIds = $user->organizations()->pluck('organizations.id');
        $branchIds = $user->branches()->pluck('branches.id');

        return $query->where(function ($q) use ($orgIds, $branchIds) {
            $q->whereIn('organization_id', $orgIds)
                ->orWhereIn('branch_id', $branchIds);
        });
    }

    public function create(array $data, ?array $nextOfKinData = null, ?array $documentsData = null): array
    {
        return DB::transaction(function () use ($data, $nextOfKinData, $documentsData) {
            $data['member_number'] = $this->numberGenerator->generate();

            $tempPassword = Str::random(12);

            $user = User::create([
                'fullname' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'username' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', ($data['first_name'] ?? '').($data['last_name'] ?? ''))).rand(100, 999),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'nida_number' => $data['national_id'] ?? ('NIDA-'.Str::random(10)),
                'password' => Hash::make($tempPassword),
                'status' => \App\Enums\UserStatus::Active,
                'is_active' => true,
            ]);

            $user->assignRole(Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']));

            $data['user_id'] = $user->id;

            $member = Member::create($data);

            if ($nextOfKinData) {
                $this->addNextOfKin($member, $nextOfKinData);
            }

            if ($documentsData) {
                foreach ($documentsData as $docData) {
                    $this->uploadDocument($member, $docData);
                }
            }

            $this->auditService->log('member.created', $member, [], $member->toArray());

            return ['member' => $member, 'temp_password' => $tempPassword];
        });
    }

    public function update(Member $member, array $data): Member
    {
        $old = $member->only(array_keys($data));

        $member->update($data);

        $this->auditService->log('member.updated', $member, $old, $data);

        return $member;
    }

    public function changeStatus(Member $member, MemberStatus $newStatus, ?string $reason = null): Member
    {
        if (! $member->membership_status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$member->membership_status->label()} to {$newStatus->label()}."
            );
        }

        $oldStatus = $member->membership_status;

        $member->update(['membership_status' => $newStatus]);

        MemberStatusHistory::create([
            'member_id' => $member->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        $this->auditService->log('member.status_changed', $member, [
            'old_status' => $oldStatus->value,
        ], [
            'new_status' => $newStatus->value,
            'reason' => $reason,
        ]);

        return $member;
    }

    public function addNextOfKin(Member $member, array $data): MemberNextOfKin
    {
        if (! empty($data['is_primary'])) {
            $member->nextOfKins()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $kin = $member->nextOfKins()->create($data);

        $this->auditService->log('member.next_of_kin_added', $member, [], $kin->toArray());

        return $kin;
    }

    public function updateNextOfKin(MemberNextOfKin $kin, array $data): MemberNextOfKin
    {
        if (! empty($data['is_primary'])) {
            $kin->member->nextOfKins()
                ->where('id', '!=', $kin->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $kin->update($data);

        return $kin;
    }

    public function removeNextOfKin(MemberNextOfKin $kin): void
    {
        $this->auditService->log('member.next_of_kin_removed', $kin->member, $kin->toArray(), []);
        $kin->delete();
    }

    public function uploadDocument(Member $member, array $data): MemberDocument
    {
        /** @var UploadedFile $file */
        $file = $data['file'];

        $safeFilename = $member->member_number.'_'.time().'_'.preg_replace('/[^a-zA-Z0-9.]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('member-documents/'.$member->id, $safeFilename, 'private');

        $document = $member->documents()->create([
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'] ?? null,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'verification_status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditService->log('member.document_uploaded', $member, [], [
            'document_type' => $data['document_type'],
            'filename' => $file->getClientOriginalName(),
        ]);

        return $document;
    }

    public function verifyDocument(MemberDocument $document, ?string $notes = null): MemberDocument
    {
        $document->update([
            'verification_status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $notes ?? $document->notes,
        ]);

        $this->auditService->log('member.document_verified', $document->member, [], [
            'document_type' => $document->document_type->value,
            'document_id' => $document->id,
        ]);

        return $document;
    }

    public function rejectDocument(MemberDocument $document, ?string $notes = null): MemberDocument
    {
        $document->update([
            'verification_status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'notes' => $notes ?? $document->notes,
        ]);

        $this->auditService->log('member.document_rejected', $document->member, [], [
            'document_type' => $document->document_type->value,
            'document_id' => $document->id,
            'reason' => $notes,
        ]);

        return $document;
    }

    public function deleteDocument(MemberDocument $document): void
    {
        Storage::disk('private')->delete($document->file_path);
        $this->auditService->log('member.document_deleted', $document->member, $document->toArray(), []);
        $document->delete();
    }

    public function archiveMember(Member $member, ?string $reason = null): Member
    {
        if ($member->membership_status !== MemberStatus::Active) {
            throw new \InvalidArgumentException('Only active members can be archived.');
        }

        return $this->changeStatus($member, MemberStatus::Inactive, $reason);
    }

    public function restoreMember(Member $member, ?string $reason = null): Member
    {
        if (! $member->trashed()) {
            throw new \InvalidArgumentException('Member is not archived.');
        }

        $member->restore();

        $this->auditService->log('member.restored', $member, [], ['reason' => $reason]);

        return $member;
    }
}
