<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberDocumentRequest;
use App\Http\Requests\VerifyDocumentRequest;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Services\MemberService;
use Illuminate\Support\Facades\Storage;

class MemberDocumentController extends Controller
{
    public function __construct(
        private MemberService $memberService,
    ) {}

    public function store(StoreMemberDocumentRequest $request, Member $member)
    {
        $this->authorize('manageDocuments', $member);

        $this->memberService->uploadDocument($member, $request->validated());

        return redirect()->route('members.show', $member)->with('tab', 'documents')
            ->with('success', 'Document uploaded successfully.');
    }

    public function download(Member $member, MemberDocument $document)
    {
        $this->authorizeForMember($member, $document);

        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Document file not found.');
        }

        return Storage::disk('private')->download(
            $document->file_path,
            $document->original_filename
        );
    }

    public function verify(VerifyDocumentRequest $request, Member $member, MemberDocument $document)
    {
        $this->authorizeForMember($member, $document);

        if ($request->verification_status === 'verified') {
            $this->memberService->verifyDocument($document, $request->notes);
            $message = 'Document verified successfully.';
        } else {
            $this->memberService->rejectDocument($document, $request->notes);
            $message = 'Document rejected.';
        }

        return redirect()->route('members.show', $member)->with('tab', 'documents')
            ->with('success', $message);
    }

    public function destroy(Member $member, MemberDocument $document)
    {
        $this->authorizeForMember($member, $document);

        $this->memberService->deleteDocument($document);

        return redirect()->route('members.show', $member)->with('tab', 'documents')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * The document and the member are bound independently from the URL, so
     * authorizing the member alone leaves the document identifier trusted from
     * user input. Without this check, a user authorized for one member could
     * read, re-verify or delete another member's identity documents by pairing
     * their own member id with any document id.
     */
    private function authorizeForMember(Member $member, MemberDocument $document): void
    {
        abort_unless($document->member_id === $member->id, 403);

        $this->authorize('manageDocuments', $member);
    }
}
