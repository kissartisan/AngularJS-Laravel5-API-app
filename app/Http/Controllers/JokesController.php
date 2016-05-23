<?php

namespace App\Http\Controllers;

use App\Joke;
use App\User;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Response;
use Illuminate\Support\Facades\Auth;

class JokesController extends Controller
{

	public function __construct()
	{
		$this->middleware('jwt.auth');
	}

    public function index(Request $request)
    {

    	# Declare a search variable
    	$search_term = $request->input('search');
    	# Declare a variable to handle user input's limit
    	$limit = $request->input('limit')?$request->input('limit'):5;

    	# If user search a keywork
    	if ($search_term) {
    		# Find the keyword that matches the body column in the table
    		$jokes = Joke::orderBy('id', 'DESC')->where('body', 'LIKE', "%$search_term%")->with(
    			array('User' => function($query) {
    				$query->select('id', 'name');
    			})
    		)->select('id', 'body', 'user_id')->paginate($limit);

    		# Append the search result and the limit in the $jokes array
    		$jokes->appends(array(
    			'search' => $search_term,
    			'limit' => $limit
    		));
    	} else {
    		# Default load
    		$jokes = Joke::orderBy('id', 'DESC')->with(
    		# Eager load constraints with a select condition
    			array('User' => function($query) {
    				$query->select('id', 'name');
    			})
    		)->select('id', 'body', 'user_id')->paginate($limit);

    		# Append the only the limit array in the $jokes array
    		$jokes->appends(array(
    			'limit' => $limit
    		));
    	}

    	# Return it via JSON with transformed columns
    	return Response::json(
    		$this->transformCollection($jokes)
    	, 200);
    }

    public function show($id)
	{
		$joke = Joke::with(
			array('User' => function($query) {
				$query->select('id', 'name');
			})
		)->find($id);
		// $joke = Joke::find($id);

		if (!$joke) {
			return Response::json([
				'error' => [
					'message' => 'Joke does not exist',
				]
			], 404);
		}

		# Get the previous joke id
		$previous = Joke::where('id', '<', $joke->id)->max('id');

		# Get the next joke id
		$next = Joke::where('id', '>', $joke->id)->min('id');

		return Response::json([
			'previous_joke_id' => $previous,
			'next_joke_id' => $next,
			'data' => $this->transform($joke),
		], 200);
	}

	public function store(Request $request)
	{
		if (! $request->body or ! $request->user_id) {
			return Response::json([
				'error' => [
					'message' => 'Please provide Both body and user_id'
				]
			], 422);
		}

		$joke = Joke::create($request->all());

		return Response::json([
			'message' => 'Joke Created Successfully',
			'data' => $this->transform($joke),
		]);
	}

	public function update(Request $request, $id)
	{
		if (!$request->body || !$request->user_id) {
			return Response::json([
				'error' => [
					'message' => 'Please Provide both body and user_id',
				]
			], 422);
		}

		$joke = Joke::find($id);
		$joke->body = $request->body;
		$joke->user_id = $request->user_id;
		$joke->save();

		return Response::json([
			'message' => 'Joke updated successfully',
		]);
	}

	public function destroy($id)
	{
		Joke::destroy($id);
	}

	// Map the id and body in the $jokes array to replace it with
	// joke_id and joke
	private function transformCollection($jokes)
	{
		$jokesArray = $jokes->toArray();
		return [
		# These data were given by the paginate() method of Laravel
			'total' => $jokesArray['total'],
			'per_page' => intval($jokesArray['per_page']),
			'current_page' => $jokesArray['current_page'],
			'last_page' => $jokesArray['last_page'],
			'next_page_url' => $jokesArray['next_page_url'],
			'prev_page_url' => $jokesArray['prev_page_url'],
			'from' => $jokesArray['from'],
			'to' => $jokesArray['to'],
		# These data were given by the paginate() method of Laravel
			'data' =>array_map([$this, 'transform'], $jokesArray['data']),
		];
	}

	// Change the  table values id to joke_id and body to joke
	// because we don't want to show the fields name as in the table
	private function transform($joke)
	{
		return [
			'joke_id' => $joke['id'],
			'joke' => $joke['body'],
			'submitted_by' => $joke['user']['name']
		];
	}

}
