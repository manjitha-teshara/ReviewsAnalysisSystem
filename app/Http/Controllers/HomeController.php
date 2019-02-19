<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Review;
use Google\Cloud\Core\ServiceBuilder;
use App\Charts\reviewsAnalytic;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        //Fetching Static Data
        $customers = User::where('role_id',1)->count();
        $reviews = Review::count();
        $staticData = [$customers, $reviews];

        //Fetching Category Data
        $rooms = Review::where('category_id',1)->count();
        $services = Review::where('category_id',2)->count();
        $foods = Review::where('category_id',3)->count();
        $facilities = Review::where('category_id',4)->count();
        $categoryData = [$rooms,$services,$foods,$facilities];

        //Fetching Data for Chart
        $excellent = Review::where('level',1)->count();
        $good = Review::where('level',2)->count();
        $neutral = Review::where('level',3)->count();
        $bad = Review::where('level',4)->count();

        // Generating Chart
        $chart = new reviewsAnalytic;
        $chart->labels(['Excellent', 'Good', 'Neutral', 'Bad']);
        $chart->dataset('Reviews Analytic', 'pie', [$excellent,$good,$neutral,$bad])->backgroundColor(['green','orange','purple','red']);
        return view('home', compact('chart','categoryData','staticData'));
    }

    public function postReview(Request $request){
        $sentiment = $this->analyze($request->review);
        $level="";
        if($sentiment['score']>=0.8){
            $level = "1";
        }else if($sentiment['score']>=0.1){
            $level = "2";
        }else if($sentiment['score']>=0.0){
            $level = "3";
        }else{
            $level = "4";
        }

        $rev = new review();
        $rev->user_id = \Auth::user()->id;
        $rev->review = $request->review;
        $rev->category_id = $request->category;
        $rev->score = $sentiment['score'];
        $rev->level = $level;
        $rev->save();
        return redirect('/home');
    }

    public function analyze($review){
        $cloud = new ServiceBuilder([
            'keyFilePath' => base_path('storage/app/public/gc.json'),
            'projectId' => 'sentimental-231106'
        ]);

        $language = $cloud->language();
        $annotation = $language->analyzeSentiment($review);
        $sentiment = $annotation->sentiment();
        return $sentiment;    
    }
}
