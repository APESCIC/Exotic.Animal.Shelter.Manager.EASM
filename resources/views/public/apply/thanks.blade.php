@extends('layouts.public')

@section('title', 'Thank you · '.$organisationName)

@section('content')
    <h1>Thank you</h1>
    <p>Your application has been received. The shelter will contact you if they need more information.</p>
    <p><a href="{{ route('public.adopt.index') }}">Back to adoptable animals</a>
        · <a href="{{ route('public.apply.index') }}">Submit another application</a></p>
@endsection
