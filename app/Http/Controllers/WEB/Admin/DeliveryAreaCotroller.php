<?php

namespace App\Http\Controllers\WEB\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeliveryArea;
use App\Models\Address;

class DeliveryAreaCotroller extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        $areas = DeliveryArea::orderBy('id','desc')->get();
        return view('admin.delivery_area',compact('areas'));

    }


    public function create()
    {
        return view('admin.delivery_area_create');
    }


    public function store(Request $request)
    {
        $rules = [
            'area_name'=>'required',
            'min_time'=>'required',
            'max_time'=>'required',
            'delivery_fee'=>'required',
            'status'=>'required',
        ];
        $customMessages = [
            'area_name.required' => trans('admin_validation.Area name is required'),
            'min_time.required' => trans('admin_validation.Minimum time is required'),
            'max_time.required' => trans('admin_validation.Maximum time is required'),
            'delivery_fee.required' => trans('admin_validation.Fee is required'),
            'status.required' => trans('admin_validation.Status is required'),

        ];
        $this->validate($request, $rules,$customMessages);

        $area = new DeliveryArea();
        $area->area_name = $request->area_name;
        $area->min_time = $request->min_time;
        $area->max_time = $request->max_time;
        $area->delivery_fee = $request->delivery_fee;
        $area->status = $request->status;
        $area->save();

        $notification= trans('admin_validation.Created Successfully');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->back()->with($notification);
    }


    public function edit($id)
    {
        $area = DeliveryArea::find($id);
        return view('admin.delivery_area_edit',compact('area'));
    }

    public function update(Request $request,$id)
    {

        $rules = [
            'area_name'=>'required',
            'min_time'=>'required',
            'max_time'=>'required',
            'delivery_fee'=>'required',
            'status'=>'required',
        ];
        $customMessages = [
            'area_name.required' => trans('admin_validation.Area name is required'),
            'min_time.required' => trans('admin_validation.Minimum time is required'),
            'max_time.required' => trans('admin_validation.Maximum time is required'),
            'delivery_fee.required' => trans('admin_validation.Fee is required'),
            'status.required' => trans('admin_validation.Status is required'),

        ];
        $this->validate($request, $rules,$customMessages);

        $area = DeliveryArea::find($id);
        $area->area_name = $request->area_name;
        $area->min_time = $request->min_time;
        $area->max_time = $request->max_time;
        $area->delivery_fee = $request->delivery_fee;
        $area->status = $request->status;
        $area->save();

        $notification= trans('admin_validation.Update Successfully');
        $notification = array('messege'=>$notification,'alert-type'=>'success');
        return redirect()->route('admin.delivery-area.index')->with($notification);
    }

    public function destroy($id)
    {
        $address = Address::where(['delivery_area_id' => $id])->count();

        if($address == 0){
            $area = DeliveryArea::find($id);
            $area->delete();

            $notification= trans('admin_validation.Delete Successfully');
            $notification = array('messege'=>$notification,'alert-type'=>'success');
            return redirect()->back()->with($notification);
        }else{
            $notification= trans('admin_validation.You can not delete this item');
            $notification = array('messege'=>$notification,'alert-type'=>'error');
            return redirect()->back()->with($notification);
        }

    }
}
