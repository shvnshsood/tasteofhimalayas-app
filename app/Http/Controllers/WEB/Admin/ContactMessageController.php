<?php

namespace App\Http\Controllers\WEB\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactMessage;
use App\Models\Setting;
class ContactMessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(){
        $contactMessages = ContactMessage::orderBy('id','desc')->get();
        $contact_setting = Setting::select('enable_save_contact_message','contact_email')->first();
        return view('admin.contact_message',compact('contactMessages','contact_setting'));
    }

    public function show($id){
        $contactMessage = ContactMessage::find($id);
        return view('admin.show_contact_message',compact('contactMessage'));
    }

    public function destroy($id){
        $contactMessage = ContactMessage::find($id);
        $contactMessage->delete();

        $notification = trans('admin_validation.Delete Successfully');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);
    }

    public function handleSaveContactMessage(Request $request){

        $rules = [
            "contact_email" => "required",
            "enable_save_contact_message" => "required",
        ];

        $customMessages = [
            "contact_email.required" => trans("Email is required"),
        ];

        $this->validate($request, $rules, $customMessages);

        $setting = Setting::first();
        $setting->contact_email = $request->contact_email;
        $setting->enable_save_contact_message = $request->enable_save_contact_message;
        $setting->save();

        $notification = trans('admin_validation.Updated Successfully');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);

    }
}
