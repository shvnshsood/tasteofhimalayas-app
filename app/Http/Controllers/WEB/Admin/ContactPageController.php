<?php

namespace App\Http\Controllers\WEB\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactPage;
use Image;
use File;
class ContactPageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(){
        $contact = ContactPage::first();

        return view('admin.contact_page', compact('contact'));
    }

    public function update(Request $request, $id){
        $rules = [
            'email' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'google_map' => 'required',
        ];
        $customMessages = [
            'email.required' => trans('admin_validation.Email is required'),
            'phone.unique' => trans('admin_validation.Phone is required'),
            'address.unique' => trans('admin_validation.Address is required'),
            'google_map.unique' => trans('admin_validation.Google Map is required'),
        ];
        $this->validate($request, $rules,$customMessages);

        $contact = ContactPage::find($id);
        if($request->image){
            $exist_banner = $contact->image;
            $extention = $request->image->getClientOriginalExtension();
            $banner_name = 'contact-us'.date('-Y-m-d-h-i-s-').rand(999,9999).'.'.$extention;
            $banner_name = 'uploads/website-images/'.$banner_name;
            Image::make($request->image)
                ->save(public_path().'/'.$banner_name);
            $contact->image = $banner_name;
            $contact->save();
            if($exist_banner){
                if(File::exists(public_path().'/'.$exist_banner))unlink(public_path().'/'.$exist_banner);
            }
        }

        $contact->email = $request->email;
        $contact->phone = $request->phone;
        $contact->address = $request->address;
        $contact->map = $request->google_map;
        $contact->save();

        $notification = trans('admin_validation.Updated Successfully');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);
    }

}
