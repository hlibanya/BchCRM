<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function get_stats()
    {
        $now = Carbon::now();

        // Arrays to store the metrics
        $meanFirstResponseTimes = [];
        $notResolvedCounts = [];
        $meanTimeToResolutions = [];
        $meanAges = [];

        // Loop over the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $start = $date->copy()->startOfDay();
            $end = $i === 0 ? $now : $date->copy()->endOfDay();

            // Metric 1: Mean First Response Time
            // Average time from creation to in_progress for requests created on this day
            $avgFirstResponse = Request::whereBetween('created_at', [$start, $end])
                ->whereNotNull('in_progress_at')
                ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, in_progress_at)) as average'))
                ->first()->average ?? 0;
            $meanFirstResponseTimes[] = round($avgFirstResponse, 2);

            // Metric 2: Number of Not Resolved Tickets
            // Count of tickets not resolved by the end of this day
            $countNotResolved = Request::where('created_at', '<=', $end)
                ->where(function ($query) use ($end) {
                    $query->whereNull('resolved_at')
                          ->orWhere('resolved_at', '>', $end);
                })
                ->count();
            $notResolvedCounts[] = $countNotResolved;

            // Metric 3: Mean Time to Resolution
            // Average time from creation to resolution for requests resolved on this day
            $avgTimeToResolution = Request::whereBetween('resolved_at', [$start, $end])
                ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) as average'))
                ->first()->average ?? 0;
            $meanTimeToResolutions[] = round($avgTimeToResolution, 2);

            // Metric 4: Mean Age of Not Resolved Tickets
            // Average age of tickets not resolved by the end of this day
            $avgAge = Request::where('created_at', '<=', $end)
                ->where(function ($query) use ($end) {
                    $query->whereNull('resolved_at')
                          ->orWhere('resolved_at', '>', $end);
                })
                ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, created_at, ?)) as average'))
                ->setBindings([$end], 'select')
                ->first()->average ?? 0;
            $meanAges[] = round($avgAge, 2);
        }

        // Return the metrics as an array
        return [
            'mean_first_response_times' => $meanFirstResponseTimes,
            'not_resolved_counts' => $notResolvedCounts,
            'mean_time_to_resolutions' => $meanTimeToResolutions,
            'mean_ages' => $meanAges,
        ];
    }
    }
}
