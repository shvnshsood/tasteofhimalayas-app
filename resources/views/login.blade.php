
@extends('layout')
@section('title')
    <title>{{__('user.Login')}}</title>
@endsection
@section('meta')
    <meta name="description" content="{{__('user.Login')}}">
@endsection

@section('public-content')

    <!--=============================
        BREADCRUMB START
    ==============================-->
    <section class="tf__breadcrumb" style="background: url({{ asset($breadcrumb) }});">
        <div class="tf__breadcrumb_overlay">
            <div class="container">
                <div class="tf__breadcrumb_text">
                    <h1>{{__('user.Login')}}</h1>
                    <ul>
                        <li><a href="{{ route('home') }}">{{__('user.Home')}}</a></li>
                        <li><a href="{{ route('login') }}">{{__('user.Login')}}</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
    <!--=============================
        BREADCRUMB END
    ==============================-->

            <!--=========================
        SIGNIN START
    ==========================-->
    <section class="tf__signin pt_100 xs_pt_70 pb_100 xs_pb_70">
        <div class="container">
            <div class="row justify-content-center wow fadeInUp" data-wow-duration="1s">
                <div class="col-xl-5 col-sm-10 col-md-8 col-lg-6">
                    <div class="tf__login_area">
                        <h2>{{__('user.Welcome back!')}}</h2>
                        <p>{{__('user.Login in to continue')}}</p>
                        <form action="{{ route('store-login') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-xl-12">
                                    <div class="tf__login_imput">
                                        <input type="email" name="email" placeholder="{{__('user.Email')}}">
                                    </div>
                                </div>
                                <div class="col-xl-12">
                                    <div class="tf__login_imput">
                                        <input type="password" name="password" placeholder="{{__('user.Password')}}">
                                    </div>
                                </div>
                                <div class="col-xl-12">
                                    <div class="tf__login_imput tf__login_check_area">
                                        <div class="form-check">
                                            <input class="form-check-input" name="remember" type="checkbox" value=""
                                                id="flexCheckDefault">
                                            <label class="form-check-label" for="flexCheckDefault">
                                                {{__('user.Remeber Me')}}
                                            </label>
                                        </div>
                                        <a href="{{ route('forget-password') }}">{{__('user.Forgot Password ?')}}</a>
                                    </div>
                                </div>

                                @if($recaptcha_setting->status==1)
                                    <div class="col-xl-12 mb-3">
                                        <div class="g-recaptcha" data-sitekey="{{ $recaptcha_setting->site_key }}"></div>
                                    </div>
                                @endif

                                <div class="col-xl-12">
                                    <div class="tf__login_imput">
                                        <button type="submit" class="common_btn">{{__('user.login')}}</button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <p class="create_account">{{__('user.Do not have an account ?')}} <a href="{{ route('register') }}">{{__('user.Register here')}}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--=========================
        SIGNIN END
    ==========================-->

@endsection
