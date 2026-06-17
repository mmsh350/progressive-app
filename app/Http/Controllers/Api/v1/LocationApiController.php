<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\State;
use App\Models\Lga;
use App\Http\Resources\StateResource;
use App\Http\Resources\LgaResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationApiController extends Controller
{
    /**
     * Get list of all states.
     */
    public function states(): AnonymousResourceCollection
    {
        return StateResource::collection(State::orderBy('name')->get());
    }

    /**
     * Get list of LGAs, optionally filtered by state.
     */
    public function lgas(Request $request): AnonymousResourceCollection
    {
        $query = Lga::query();

        if ($request->has('state_id')) {
            $query->where('state_id', (int) $request->state_id);
        }

        return LgaResource::collection($query->orderBy('name')->get());
    }
}
