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
        $this->authorize('manageDocuments', $member);

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
        $this->authorize('manageDocuments', $member);

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
        $this->authorize('manageDocuments', $member);

        $this->memberService->deleteDocument($document);

        return redirect()->route('members.show', $member)->with('tab', 'documents')
            ->with('success', 'Document deleted successfully.');
    }
}
