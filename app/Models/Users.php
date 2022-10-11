<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
//use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Auth\Passwords\CanResetPassword;
use App\Models\Booking;
use App\Models\Review;

class Users extends Authenticatable 
//implements JWTSubject 
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'users';
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'created_by'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
   /* public function getJWTIdentifier() {
        return $this->getKey();
    }*/
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    /*public function getJWTCustomClaims() {
        return [];
    }   */ 
    public function booking()
      {
            return $this->hasMany(Booking::class);   
      } 
      public function review()
      {
            return $this->hasMany(Review::class);   
      } 
      public function recentSearch()
      {
            return $this->hasOne(RecentSearch::class);   
      }

}
