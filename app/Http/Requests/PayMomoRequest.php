<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\PayMomoRule;
class PayMomoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function rules()
    {
        return [

            'request_type' => ['required',
                 new PayMomoRule(),

            ],
            'money' => 'required|integer|min:10000',
            'redirectUrl' => 'required',
        ];
    }
    public function messages() {
        return [
            'request_type.required'  => 'Mời chọn phương thức nạp !',
            'money.required'  => 'Mời nhập số tiền !',
            'money.integer'  => 'Số tiền không hợp lệ !',
            'money.min'  => 'Số tiền nạp tối thiểu 10.000đ !',
        ];
    }
}
