<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use DTApi\Http\Requests\JobRequest;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * Display a listing of jobs for the user or admin.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->__authenticatedUser;
        $response = $user->isAdmin() 
                    ? $this->repository->getAll($request) 
                    : $this->repository->getUsersJobs($user->id);

        return response()->json($response);
    }

    /**
     * Show job details by ID.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response()->json($job);
    }

    /**
     * Store a newly created job.
     * @param JobRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(JobRequest $request)
    {
        $data = $request->validated();
        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response()->json($response);
    }

    /**
     * Update the specified job by ID.
     * @param int $id
     * @param JobRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(int $id, JobRequest $request)
    {
        $data = $request->validated();
        $response = $this->repository->updateJob($id, $data, $request->__authenticatedUser);

        return response()->json($response);
    }

    /**
     * Send immediate job email.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $response = $this->repository->storeJobEmail($data);

        return response()->json($response);
    }

    /**
     * Get job history for the user.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');
        if ($user_id) {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response()->json($response);
        }

        return response()->json(['error' => 'User ID is required'], 400);
    }

    /**
     * Accept a job.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptJob(Request $request)
    {
        $data = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->acceptJob($data, $request->__authenticatedUser);

        return response()->json($response);
    }

    /**
     * Accept a job with ID.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptJobWithId(Request $request)
    {
        $job_id = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->acceptJobWithId($job_id, $request->__authenticatedUser);

        return response()->json($response);
    }

    /**
     * Cancel a job.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelJob(Request $request)
    {
        $data = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->cancelJobAjax($data, $request->__authenticatedUser);

        return response()->json($response);
    }

    /**
     * End a job session.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function endJob(Request $request)
    {
        $data = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->endJob($data);

        return response()->json($response);
    }

    /**
     * Notify that the customer did not call.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerNotCall(Request $request)
    {
        $data = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->customerNotCall($data);

        return response()->json($response);
    }

    /**
     * Get potential jobs for a user.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPotentialJobs(Request $request)
    {
        $response = $this->repository->getPotentialJobs($request->__authenticatedUser);

        return response()->json($response);
    }

    /**
     * Update job distance and session details.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->validate([
            'jobid' => 'required|integer',
            'distance' => 'nullable|string',
            'time' => 'nullable|string',
            'session_time' => 'nullable|string',
            'flagged' => 'nullable|boolean',
            'manually_handled' => 'nullable|boolean',
            'by_admin' => 'nullable|boolean',
            'admincomment' => 'nullable|string'
        ]);

        $jobId = $data['jobid'];
        $this->updateDistance($data);
        $this->updateJobStatus($data);

        return response()->json(['message' => 'Record updated!']);
    }

    /**
     * Reopen a job.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reopen(Request $request)
    {
        $data = $request->validate(['job_id' => 'required|integer']);
        $response = $this->repository->reopen($data);

        return response()->json($response);
    }

    /**
     * Resend notifications to translators.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->validate(['jobid' => 'required|integer']);
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);
        
        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return response()->json(['success' => 'Push sent']);
    }

    /**
     * Resend SMS notifications to the translator.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->validate(['jobid' => 'required|integer']);
        $job = $this->repository->find($data['jobid']);
        
        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response()->json(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper function to update job distance and time.
     * @param array $data
     */
    private function updateDistance(array $data)
    {
        if (!empty($data['distance']) || !empty($data['time'])) {
            Distance::where('job_id', $data['jobid'])->update([
                'distance' => $data['distance'] ?? '',
                'time' => $data['time'] ?? '',
            ]);
        }
    }

    /**
     * Helper function to update job session and admin status.
     * @param array $data
     */
    private function updateJobStatus(array $data)
    {
        Job::where('id', $data['jobid'])->update([
            'admin_comments' => $data['admincomment'] ?? '',
            'flagged' => $data['flagged'] ? 'yes' : 'no',
            'session_time' => $data['session_time'] ?? '',
            'manually_handled' => $data['manually_handled'] ? 'yes' : 'no',
            'by_admin' => $data['by_admin'] ? 'yes' : 'no',
        ]);
    }
}
