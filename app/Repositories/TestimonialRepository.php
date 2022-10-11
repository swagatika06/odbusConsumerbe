<?php
namespace App\Repositories;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
class TestimonialRepository
{
    protected $testimonial;
    public function __construct(Testimonial $testimonial )
    {
       $this->testimonial = $testimonial ;
    }    
    public function getAll($user_id)
    {
      return $this->testimonial->where('user_id', $user_id)
                                ->where('status','1')
                                ->orderBy('id','DESC')->get();
    }
}