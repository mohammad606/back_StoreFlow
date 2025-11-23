<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class authController extends Controller
{
    public function register(Request $req){
        $req->validate([
            'name'=>'required|string|max:255',
            'email'=>'required|string|email|max:255',
            'password'=>'required|string|min:6'
        ]);
        $user = User::create([
            'name'=>$req->name,
            'email'=>$req->email,
            'password'=>Hash::make($req->password)
        ]);
        $token = Auth::login($user);
        return response()->json([
            'status'=> 'success',
            'massage' => 'user created successfully',
            'user'=> $user,
            'authorisation'=>[
                'token'=> $token,
                'type'=> 'bearer',
            ]
        ]);
    }


}
