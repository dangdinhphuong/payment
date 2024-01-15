<?php

namespace App\Http\Controllers\Momo;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayMomoRequest;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMentController extends Controller
{
    public function store(Request $request)
    {
       $data =  $request->all();
        $orderId =  time() . ""; // Mã đơn hàng
        $orderInfo = "Thanh toán qua MoMo";
        $amount = $data['money'];
        $ipnUrl =$data['redirectUrl'];
        $redirectUrl = $data['redirectUrl'];
        $extraData = "";
        $requestId = time() . "";
        $requestType = $data['request_type'];

        $result = $this->setData($orderInfo, $amount, $orderId, $redirectUrl, $ipnUrl, $requestId, $requestType, $extraData);
        return $result;

    }
    public function setData($orderInfo, $amount, $orderId, $redirectUrl, $ipnUrl, $requestId, $requestType, $extraData = "")
    {

        $endpoint = env('URL_MOMO');
        $partnerCode =env('PARTNER_CODE_MOMO');
        $accessKey = env('ACCESS_KEY_MOMO');
        $serectkey = env('SERECT_KEY_MOMO');


        //before sign HMAC SHA256 signature
        $rawHash = "accessKey=" . $accessKey . "&amount=" . $amount . "&extraData=" . $extraData . "&ipnUrl=" . $ipnUrl . "&orderId=" . $orderId . "&orderInfo=" . $orderInfo . "&partnerCode=" . $partnerCode . "&redirectUrl=" . $redirectUrl . "&requestId=" . $requestId . "&requestType=" . $requestType;
        $signature = hash_hmac("sha256", $rawHash, $serectkey);
        $data = array(
            'partnerCode' => $partnerCode,
            'partnerName' => "Test",
            "storeId" => env('APP_NAME'),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => $signature
        );
        return $this->sendPayMomo($endpoint, $data);;
    }

    function sendPayMomo($url, $data)
    {
        $response = Http::post($url, $data);
        return json_decode($response, true);
    }
     // to do create trans_log
    function apiRequestMomo(Request $request)
    {
        $data = $request->all();
        if(!isset($data['requestId']) || empty($data['requestId']) || !isset($data['amount']) || empty($data['amount'])){
            return redirect()->route('momo')->with(['error' => 'Nạp thẻ thất bại chưa được thanh toán !']);
        }
        $password = auth()->user()->realname.auth()->user()->password.$data['requestId'].$data['amount'];
        $token = $this->hashSHA256($password,auth()->user()->salt);

        if($token['pass'] === auth()->user()->token){
            $message ="";
            try {
                DB::beginTransaction();
                User::where("id", auth()->user()->id)->update([
                    'token' => "",
                    'cash'  => auth()->user()->cash+$data['amount'],
                ]);
                TransLogMomo::create([
                    "name"=> auth()->user()->realname,
                    "amount"=> $data['amount'],
                    "trans_id"=> $data['transId'],
                    "requestId"=> $data['requestId'],
                    "date"=> Carbon::now(),
                    "type"=> $data['orderType']
                ]);
                DB::commit();
                $message ="Thành công";
            } catch (Exception $exception) {
                DB::rollBack();
                Log::error($message);
                Log::info("thanh toán momo thất bại :" . auth()->user()->realname.'|id:'.auth()->user()->id.'|money:'.$data['amount'].'|transId:'.$data['transId'].'|exception:'.$exception);
                $message ="Thât bại";

            }
            Log::info("Thanh toán momo thành công :" . auth()->user()->realname.'|id:'.auth()->user()->id.'|money:'.$data['amount'].'|transId:'.$data['transId'].'|message:'.$message);

            if($message == "Thành công"){
                return redirect()->route('momo')->with('success', "Nạp thẻ thành công !");
            }

            return redirect()->back()->with(['error' => 'Nạp thẻ thất bại , vui lòng liên hệ với admin sớm nhất !']);

        }
    }

}

