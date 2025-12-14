<?php

namespace App\Http\Responses;

use App\Http\Controllers\Controller;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse extends Controller implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($this->redirectToDashboard($request->user()));
    }
}
