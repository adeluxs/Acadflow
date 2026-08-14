<?php

namespace App\Http\Controllers;

use App\Services\FeatureAccessService;

use App\Enums\NotificationType;
use App\Services\NotificationService;
use App\Services\PdfService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TestController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private SubscriptionService $subscriptionService
    ) {}

    /**
     * Test endpoint to verify subscription limits
     */
    public function testSubscription()
    {
        $user = Auth::user();
        $summary = $this->subscriptionService->getSubscriptionSummary($user);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'role' => $user->role,
            ],
            'subscription' => $summary,
            'can_upload_5mb' => $this->subscriptionService->canUploadMaterial($user, 5 * 1024 * 1024),
            'can_create_course' => $this->subscriptionService->canCreateCourse($user),
        ]);
    }

    /**
     * Test notification sending
     */
    public function testNotification(Request $request)
    {
        $user = Auth::user();

        $this->notificationService->send(
            $user,
            NotificationType::SUBMISSION_RECEIVED,
            'Test Notification',
            'This is a test notification from AcadFlow.',
            ['test' => true]
        );

        return back()->with('success', 'Test notification sent!');
    }

    /**
     * Test PDF generation
     */
    public function testPdf()
    {
        $user = Auth::user();
        $pdfService = app(PdfService::class);

        $pdfContent = $pdfService->generateTranscript($user);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="test_transcript.pdf"',
        ]);
    }

    /**
     * Test feature flag check
     */
    public function testFeatureFlag()
    {
        $user = Auth::user();

        $flags = [
            'pwa_enabled' => FeatureAccessService::canAccessFeature('pwa_enabled', $user),
            'push_notifications' => FeatureAccessService::canAccessFeature('push_notifications', $user),
            'advanced_analytics' => FeatureAccessService::canAccessFeature('advanced_analytics', $user),
            'documents_exports' => FeatureAccessService::canAccessFeature('documents_exports', $user),
            'course_certificates' => FeatureAccessService::canAccessFeature('course_certificates', $user),
        ];

        return response()->json([
            'user' => $user->full_name,
            'role' => $user->role,
            'feature_flags' => $flags,
        ]);
    }
}
