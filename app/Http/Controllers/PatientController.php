<?php

namespace App\Http\Controllers;

use App\Appointment;
use App\Clinic;
use App\CurrentPatient;
use App\Http\Controllers\Redirect;
use App\Http\Resources\PatientPedingResource;
use App\inpatient;
use App\Medicine;
use App\Patient;
use App\PatientVisit;
use App\Prescription;
use App\Prescription_Medicine;
use App\Ward;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use stdClass;

class PatientController extends Controller
{
    protected $wardArray;

    public function __construct()
    {
        $this->middleware('auth');
        $this->wardList = ['' => 'Select Ward No'] + Ward::pluck('id', 'ward_no')->all();
    }

    public function inPatientReport()
    {

        return view('patient.inpatient.inpatients', ["date"=>null,"title" => "Inpatient Details", "data_count" => 0]);

    }

    public function inPatientReportData(Request $request)
    {
        $data=DB::table('inpatients')->whereDate('created_at', '=', $request->date)->get();
        if($data->count()>0){
            return view('patient.inpatient.inpatients', ["title" => "Inpatient Details","date"=>$request->date,"data_count"=>$data->count(), "data" => $data]);

        }else{
            return redirect(route("inPatientReport"))->with('fail',"No Results Found");
        }

    }

    public function index()
    {
        $user = Auth::user();
        return view('patient.register_patient', ['title' => $user->name]);
    }

    public function patientHistory($id)
    {
        $prescs = Prescription::where('patient_id', $id)->orderBy('created_at', 'desc')->get();
        $title = "Patient History ($id)";

        $patient = Patient::withTrashed()->find($id);
        $hospital_visits = 1;
        $status = "Active";
        $last_seen = explode(" ", $patient->updated_at)[0];
        if ($patient->trashed()) {
            $status = "Inactive";
        }
        $hospital_visits += Prescription::where('patient_id', $patient->id)->count();

        return view('patient.history.index', compact('prescs', 'patient', 'title', 'hospital_visits', 'status', 'last_seen'));
    }

    public function patientProfileIntro(Request $request)
    {
        if ($request->has('pid')) {
            return redirect()->route('patientProfile', $request->pid);
        } else {
            return view('patient.profile.intro', ['title' => "Patient Profile"]);
        }
    }

    public function patientDelete($id, $action)
    {
        if ($action == "delete") {
            Patient::find($id)->delete();
        }if ($action == 'restore') {
            Patient::withTrashed()->find($id)->restore();
        }
        return redirect()->route('patientProfile', $id);
    }

    public function patientProfile($id)
    {
        $patient = Patient::withTrashed()->find($id);
        $hospital_visits = 1;
        $status = "Active";
        $last_seen = explode(" ", $patient->updated_at)[0];
        if ($patient->trashed()) {
            $status = "Inactive";
        }
        $hospital_visits += Prescription::where('patient_id', $patient->id)->count();

        return view('patient.profile.profile',
            [
                'title' => $patient->name,
                'patient' => $patient,
                'status' => $status,
                'last_seen' => $last_seen,
                'hospital_visits' => $hospital_visits,

            ]);
    }

    public function searchPatient(Request $request)
    {
        return view('patient.search_patient_view', ['title' => "Search Patient", "old_keyword" => null, "search_result" => ""]);
    }

    // Danh sach cho benh nhan de kham
    public function patientData(Request $request)
    {
        $result = PatientVisit::query()
            ->join('patients', 'patient_visits.patient_id', '=', 'patients.id')
            ->select('patients.*','patient_visits.*','patient_visits.stt as stt')
            ->when($request->cat == "name", function ($query) use ($request) {
                return $query->where('patients.name', 'LIKE', '%' . $request->keyword . '%');
            })
            ->when($request->cat == "nic", function ($query) use ($request) {
                return $query->where('patients.nic', 'LIKE', '%' . $request->keyword . '%');
            })
            ->when($request->cat == "telephone", function ($query) use ($request) {
                return $query->where('patients.telephone', 'LIKE', '%' . $request->keyword . '%');
            })
            ->where('department_id', '=', \auth()->user()->department_id)
            ->where('status', 0)
            ->whereDate('patient_visits.created_at', Carbon::today())
            ->orderBy('patient_visits.created_at')
            ->get();
        $currentPatient = CurrentPatient::query()
            ->where('department_id', \auth()->user()->department_id)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->first();

        $result = PatientPedingResource::make($result)->resolve();
        return view('patient.search_patient_view',
            [
                "title" => "Search Results",
                "old_keyword" => $request->keyword,
                "search_result" => $result,
                "currentPatient" => $currentPatient,
            ]
        );
    }
    // Danh sach lich hen kham
    public function listAppointment(Request $request)
    {
        // Lấy ngày mai
        $tomorrow = Carbon::tomorrow();

        $result = PatientVisit::query()
            ->join('patients', 'patient_visits.patient_id', '=', 'patients.id')
            ->select('patients.*', 'patient_visits.*', 'patient_visits.id as stt')
            ->when($request->cat == "name", function ($query) use ($request) {
                return $query->where('patients.name', 'LIKE', '%' . $request->keyword . '%');
            })
            ->when($request->cat == "nic", function ($query) use ($request) {
                return $query->where('patients.nic', 'LIKE', '%' . $request->keyword . '%');
            })
            ->when($request->cat == "telephone", function ($query) use ($request) {
                return $query->where('patients.telephone', 'LIKE', '%' . $request->keyword . '%');
            })
            ->where('department_id', '=', \auth()->user()->department_id)
            ->where('patient_visits.created_at', '>=', $tomorrow)
            ->orderBy('patient_visits.created_at')
            ->get();

        // Phân loại các bản ghi theo ngày
        $result = $result->groupBy(function ($item) {
            return \Carbon\Carbon::parse($item->created_at)->format('Y-m-d'); // Lấy định dạng ngày
        });

        return view('patient.lichhen',
            [
                "title" => "Search Results",
                "old_keyword" => $request->keyword,
                "search_result" => $result,
            ]
        );
    }


    // Khi bac si an nut hoan thanh
    public function done($stt)
    {
        PatientVisit::query()->where('stt','=', $stt)->update(['status' => 1]);

        $currentPatient = CurrentPatient::query()
            ->where('department_id', \auth()->user()->department_id)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->first();

        $currentSTT = intval($currentPatient->stt);
        $currentPatient->stt = $currentSTT + 1;
        $currentPatient->save();

        return response()->json([
            'msg' => 'Ok'
        ]);
    }

    public function nextDepartment(Request $request)
    {
        $stt = $request->stt;
        $trieu_chung = $request->trieu_chung;
        $department_id = $request->department_id;

        $patientVisit = PatientVisit::query()
            ->where('stt','=', $stt)
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->first();

        $patient = Patient::query()->where('id', '=', $patientVisit->patient_id)->first();

        $this->done($stt);
        $this->registerPatientVisit($patient->id, $trieu_chung, $department_id);

    }

    public function scan()
    {
        $client = new Client();

        try {
            // Gửi request tới API
            $response = $client->post('https://crow-wondrous-asp.ngrok-free.app/command', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'command' => 'scan_qr',
                ],
                'timeout' => 0 // Đặt thời gian chờ không giới hạn
            ]);

            // Kiểm tra mã trạng thái phản hồi
            if ($response->getStatusCode() == 200) {
                // Xử lý phản hồi thành công
                // Du lieu chua duoc xu ly
                // 089202017098|352576714|Lê Văn Lương|23052002|Nam|
                $responseBody = json_decode($response->getBody()->getContents(), true);

                // Giả sử API trả về `data` trong phản hồi JSON
                if (isset($responseBody['data'])) {
                    $data = $this->getDataFromCCCD($responseBody['data']); // du lieu da duoc xu ly
                    return response()->json($data, Response::HTTP_OK);
                } else {
                    // Trường hợp dữ liệu không như mong đợi
                    return response()->json(['error' => 'Invalid data format'], Response::HTTP_BAD_REQUEST);
                }
            } else {
                // Trường hợp mã trạng thái không phải 200
                return response()->json([
                    'error' => 'Request failed with status: ' . $response->getStatusCode()
                ], $response->getStatusCode());
            }

        } catch (\Exception $e) {
            // Xử lý lỗi ngoại lệ
            return response()->json([
                'error' => 'Request failed with exception: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getDataFromCCCD(string $data)
    {
        $arrData = explode("|", $data);
        $strBirthday = $arrData[3];
//        $birthday = substr($strBirthday, 0, 2).'-'. substr($strBirthday, 2,2). '-'. substr($strBirthday, 4);
        $birthday = substr($strBirthday, 4).'-'. substr($strBirthday, 2,2). '-'. substr($strBirthday, 0,2);
        return [
            'stt' => rand(0,1000),
            'bn_name' => $arrData[2],
            'dob' => $birthday,
            'gender' => $arrData[4],
            'birthplace' => $arrData[5],
            'arrival_time' => Carbon::now('Asia/Ho_Chi_Minh')->toDateTimeString(),
            'department' => 'Khoa CNTT',
            'cccd' => $arrData[0],
        ];
    }

    public function register(Request $request)
    {
        $data = $request->all();
        $client = new Client();
        $department = DB::table('departments')->where('id', '=', $data['department'])->first();
//        $response = $client->post('crow-wondrous-asp.ngrok-free.app/print', [
//            'stt' => '123',
//            'fullname' => $this->removeVietnameseAccents($data['fullname']),
//            'cccd' => $this->removeVietnameseAccents($data['cccd']),
//            'gender' => $this->removeVietnameseAccents($data['gender']),
//            'birthday' => $this->removeVietnameseAccents($data['birthday']),
//            'address' => $this->removeVietnameseAccents($data['address']),
////            'email' => $this->removeVietnameseAccents($data['email']),
//            'phone' => $this->removeVietnameseAccents($data['phone']),
//            'arrival_time' => Carbon::now('Asia/Ho_Chi_Minh')->toDateTimeString(),
//            'department' => $this->removeVietnameseAccents($department->department_name),
//            'trieu_chung' => $this->removeVietnameseAccents($data['trieu_chung']),
//        ]);
        $id = Patient::query()->orderBy('id', 'desc')->first()->id;
        $patient = Patient::query()->where('nic', '=', $data['cccd'])->first();
        if (!$patient) {
            $patient = Patient::query()->create([
                'id' => $id + 1,
                'name' => $data['fullname'],
                'address' => $data['address'],
                'sex' => $data['gender'] == 'Nam' ? 'Male' : 'Female',
                'bod' => $data['birthday'],
                'telephone' => $data['phone'],
                'nic' => $data['cccd'],
            ]);
        }
        $this->registerPatientVisit($patient->id, $data['trieu_chung'], $department->id);
//        if ($response->getStatusCode() == 200) {
//            return $response->getBody()->getContents();
//        } else {
//            return response()->json(['error' => 'API request failed'], 500);
//        }

    }

    function removeVietnameseAccents($str) {
        $accents = [
            'a' => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ'],
            'e' => ['è', 'é', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ'],
            'i' => ['ì', 'í', 'ỉ', 'ĩ', 'ị'],
            'o' => ['ò', 'ó', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ'],
            'u' => ['ù', 'ú', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự'],
            'y' => ['ỳ', 'ý', 'ỷ', 'ỹ', 'ỵ'],
            'd' => ['đ'],
            'A' => ['À', 'Á', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ'],
            'E' => ['È', 'É', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ'],
            'I' => ['Ì', 'Í', 'Ỉ', 'Ĩ', 'Ị'],
            'O' => ['Ò', 'Ó', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ'],
            'U' => ['Ù', 'Ú', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự'],
            'Y' => ['Ỳ', 'Ý', 'Ỷ', 'Ỹ', 'Ỵ'],
            'D' => ['Đ'],
        ];

        foreach ($accents as $nonAccent => $accentedChars) {
            $str = str_replace($accentedChars, $nonAccent, $str);
        }

        return $str;
    }

    public function registerPatientVisit($patient_id, $trieu_chung, $department_id, $stt = null)
    {
        PatientVisit::query()->create([
            'patient_id' => $patient_id,
            'stt' => $stt ?? PatientVisit::query()->orderBy('created_at', 'desc')->first()->stt + 1,
            'department_id' => $department_id,
            'trieu_chung' => $trieu_chung,
        ]);
    }

    public function registerPatient(Request $request)
    {
        try {
            $patient = new Patients;
            $today_regs = (int) Patients::whereDate('created_at', date("Y-m-d"))->count();

            $number = $today_regs + 1;
            $year = date('Y') % 100;
            $month = date('m');
            $day = date('d');

            $reg_num = $year . $month . $day . $number;

            $date = date_create($request->reg_pbd);

            $patient->id = $reg_num;
            $patient->name = $request->reg_pname;
            $patient->address = $request->reg_paddress;
            $patient->occupation = $request->reg_poccupation;
            $patient->sex = $request->reg_psex;
            $patient->bod = date_format($date, "Y-m-d");
            $patient->telephone = $request->reg_ptel;
            $patient->nic = $request->reg_pnic;
            $patient->image = $reg_num . ".png";

            $patient->save();
            session()->flash('regpsuccess', 'Patient ' . $request->reg_pname . ' Registered Successfully !');
            session()->flash('pid', "$reg_num");

            $image = $request->regp_photo; // your base64 encoded
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            \Storage::disk('local')->put("public/" . $reg_num . ".png", base64_decode($image));

            // Log Activity
            activity()->performedOn($patient)->withProperties(['Patient ID' => $reg_num])->log('Patient Registration Success');

            return redirect()->back();
        } catch (\Exception $e) {
            // do task when error
            $error = $e->getCode();
            // log activity
            activity()->performedOn($patient)->withProperties(['Error Code' => $error, 'Error Message' => $e->getMessage()])->log('Patient Registration Failed');

            if ($error == '23000') {
                session()->flash('regpfail', 'Patient ' . $request->reg_pname . ' Is Already Registered..');
                return redirect()->back();
            }
        }
    }

    public function validateAppNum(Request $request)
    {
        $num = $request->number;
        $numlength = strlen((string) $num);
        if ($numlength < 5) { // this means the appointment number has entered
            $rec = DB::table('appointments')
            ->join('patients', 'appointments.patient_id', '=', 'patients.id')
            ->select('patients.name as name', 'appointments.number as num', 'appointments.patient_id as pnum')
            ->whereRaw(DB::Raw("Date(appointments.created_at)=CURDATE() and appointments.number='$num'"))->first();
            if ($rec) {
                return response()->json([
                    "exist" => true,
                    "name" => $rec->name,
                    "appNum" => $rec->num,
                    "pNum" => $rec->pnum,
                    "finger"=>Auth::user()->fingerprint ,
                ]);
            } else {
                return response()->json([
                    "exist" => false,
                ]);
            }
        } else { //this means the patient registration number has entered
            $rec = DB::table('appointments')->join('patients', 'appointments.patient_id', '=', 'patients.id')->select('patients.name as name', 'appointments.number as num', 'appointments.patient_id as pnum')->whereRaw(DB::Raw("Date(appointments.created_at)=CURDATE() and completed='NO' and appointments.patient_id='$num'"))->first();
            if ($rec) {
                return response()->json([
                    "exist" => true,
                    "name" => $rec->name,
                    "appNum" => $rec->num,
                    "pNum" => $rec->pnum,
                ]);
            } else {
                return response()->json([
                    "exist" => false,
                ]);
            }
        }
    }

    public function checkPatientView()
    {
        $user = Auth::user();
        return view('patient.check_patient_intro', ['title' => "Check Patient"]);
    }

    public function checkPatient(Request $request)
    {
        //to get the latest appointment number for the day
        $appointment = Appointment::where('number', $request->appNum)->where('created_at', '>=', date('Y-m-d') . ' 00:00:00')->where('patient_id', $request->pid)->orderBy('created_at', 'desc')->first();

        if ($appointment->completed == "YES") {
            return redirect()->route('check_patient_view')->with('fail', "This Appointment Has Already Been Channeled.");
        }

        $patient = Patients::find($appointment->patient_id);

        $user = Auth::user();

        //need to get the latest issued prescription to fetch the patient bp,sugar,cholestrol to be displayed in the checkpatient
        $prescriptions = Prescription::where('patient_id', $appointment->patient_id)->orderBy('created_at', 'DESC')->get();

        //creates thress objects to store these data
        //sometimes thses may get blank so use the flag to resolve this issue if flag is false these will not be displayed in the view
        $pBloodPressure = new stdClass;
        $pBloodPressure->flag = false;

        $pBloodSugar = new stdClass;
        $pBloodSugar->flag = false;

        $pCholestrol = new stdClass;
        $pCholestrol->flag = false;

        foreach ($prescriptions as $prescription) {

            if (!$pBloodPressure->flag == true) {
                $bp = json_decode($prescription->bp)->value;
                if ($bp != null) {
                    $pBloodPressure->sys = explode("/", $bp)[0];
                    $pBloodPressure->dia = explode("/", $bp)[1];
                    $pBloodPressure->date = json_decode($prescription->bp)->updated;
                    $pBloodPressure->flag = true;

                }
            }

            if (!$pCholestrol->flag == true) {
                $cholestrol = json_decode($prescription->cholestrol)->value;
                if ($cholestrol != null) {
                    $pCholestrol->value = $cholestrol;
                    $pCholestrol->date = json_decode($prescription->cholestrol)->updated;
                    $pCholestrol->flag = true;
                }
            }

            if (!$pBloodSugar->flag == true) {
                $sugar = json_decode($prescription->blood_sugar)->value;
                if ($sugar != null) {
                    $pBloodSugar->value = $sugar;
                    $pBloodSugar->date = json_decode($prescription->blood_sugar)->updated;
                    $pBloodSugar->flag = true;
                }
            }

        }

        $updated = "No Previous Visits";
        if ($prescriptions->count() > 0) {
            $updated = explode(" ", $prescriptions[0]->created_at)[0];
        }
        // $updated = explode(" ", $prescriptions[0]->created_at)[0];

        $pHistory = new stdClass;

        $assinged_clinics = Patients::find($request->pid)->clinics;

        $clinics = Clinic::all();

        return view('patient.check_patient_view', [
            'title' => "Check Patient",
            'appNum' => $request->appNum,
            'appID' => $appointment->id,
            'pName' => $appointment->patient->name,
            'pSex' => $appointment->patient->sex,
            'pAge' => $patient->getAge(),
            'pCholestrol' => $pCholestrol,
            'pBloodSugar' => $pBloodSugar,
            'pBloodPressure' => $pBloodPressure,
            // 'pHistory' => $pHistory,
            'inpatient' => $appointment->admit,
            'pid' => $appointment->patient->id,
            'medicines' => Medicine::all(),
            'updated' => $updated,
            'assinged_clinics' => $assinged_clinics,
            'clinics' => $clinics,
        ]);
    }

    public function addToClinic(Request $request)
    {
        foreach ($request->clinic as $clinic) {
            $c = Clinic::find($clinic);
            $c->addPatientToClinic($request->pid);
        }
        $assinged_clinics = Patients::find($request->pid)->clinics;
        $clinics = Clinic::all();
        $pid = $request->pid;
        $html_list = view('patient.patinet_clinic', compact('pid', 'assinged_clinics', 'clinics'))->render();
        $html_already = view('patient.patient_clinic_registered', compact('assinged_clinics', 'clinics'))->render();
        return response()->json([
            'code' => 200,
            'html_already' => $html_already,
            'html_list' => $html_list,
        ]);

    }

    public function markInPatient(Request $request)
    {
        $pid = $request->pid;
        $app_num = $request->app_num;
        $user = Auth::user();
        $appointment = Appointment::where('number', $app_num)->where('created_at', '>=', date('Y-m-d') . ' 00:00:00')->where('patient_id', $pid)->first();
        if ($appointment->admit == "NO") {
            $appointment->admit = "YES";
            $appointment->doctor_id = $user->id;
            $appointment->save();
            return response()->json([
                'success' => true,
                'appid' => $appointment->id,
                'pid' => $pid,
                'app_num' => $app_num,
            ]);
        }
    }

    public function checkPatientSave(Request $request)
    {

        $user = Auth::user();
        $presc = new Prescription;
        $presc->doctor_id = $user->id;
        $presc->patient_id = $request->patient_id;
        $presc->diagnosis = $request->diagnosis;
        $presc->appointment_id = $request->appointment_id;

        $presc->medicines = json_encode($request->medicines);

        $bp = new stdClass;
        $bp->value = $request->pressure;
        $bp->updated = Carbon::now()->toDateTimeString();
        $presc->bp = json_encode($bp);

        $gloucose = new stdClass;
        $gloucose->value = $request->glucose;
        $gloucose->updated = Carbon::now()->toDateTimeString();
        $presc->blood_sugar = json_encode($gloucose);

        $cholestrol = new stdClass;
        $cholestrol->value = $request->cholestrol;
        $cholestrol->updated = Carbon::now()->toDateTimeString();
        $presc->cholestrol = json_encode($cholestrol);

        $presc->save();

        $appointment = Appointment::find($request->appointment_id);
        $appointment->completed = "YES";
        $appointment->doctor_id = $user->id;
        $appointment->save();

        foreach ($request->medicines as $medicine) {
            $med = Medicine::where('name_english', strtolower($medicine['name']))->first();
            $pres_med = new Prescription_Medicine;
            $pres_med->medicine_id = $med->id;
            $pres_med->prescription_id = $presc->id;
            $pres_med->note = $medicine['note'];
            $pres_med->save();
        }

        // Log Activity
        activity()->performedOn($presc)->withProperties(['Patient ID' => $request->patient_id, 'Doctor ID' => $user->id, 'Prescription ID' => $presc->id, 'Appointment ID' => $request->appointment_id, 'Medicines' => json_encode($request->medicines)])->log('Check Patient Success');

        return http_response_code(200);
    }

    public function create_channel_view()
    {
        $user = Auth::user();
        $appointments = DB::table('appointments')->join('patients', 'appointments.patient_id', '=', 'patients.id')->select('patients.name', 'appointments.number', 'appointments.patient_id')->whereRaw(DB::Raw('Date(appointments.created_at)=CURDATE()'))->orderBy('appointments.created_at', 'desc')->get();

        return view('patient.create_channel_view', ['title' => "Channel Appointments", 'appointments' => $appointments]);
    }

    public function regcard($id)
    {
        $patient = Patients::find($id);
        $url = Storage::url($id . '.png');
        $data = [
            'name' => $patient->name,
            'sex' => $patient->sex,
            'id' => $patient->id,
            'reg' => explode(" ", $patient->created_at)[0],
            'dob' => $patient->bod,
            'url' => $url,
        ];
        return view('patient.patient_reg_card', $data);
    }

    public function register_in_patient_view()
    {
        $user = Auth::user();
        $data = DB::table('wards')
                    ->select('*')
                    ->join('users', 'wards.doctor_id', '=', 'users.id')
                    ->get();
        // dd($data);
        return view('patient.register_in_patient_view', ['title' => "Register Inpatient",'data'=>$data]);
    }

    public function regInPatientValid(Request $request)
    {
        $pNum = $request->pNum;
        $pNumLen = strlen((string) $pNum);
        if($pNumLen < 5) //if appointemnt number have been given
        {
            $patient = DB::table('patients')
            ->join('appointments', 'patients.id', '=', 'appointments.patient_id')
            ->select('patients.id as id', 'patients.name as name', 'patients.sex as sex', 'patients.address as address', 'patients.occupation as occ', 'patients.telephone as tel', 'patients.nic as nic', 'appointments.admit as ad', 'patients.bod as bod','appointments.number as appnum','appointments.doctor_id as D1', 'patients.updated_at')
            ->whereRaw(DB::Raw("appointments.admit='YES' and appointments.number='$pNum'"))
            ->first();

            if ($patient) {

            return response()->json([
                'exist' => true,
                'name' => $patient->name,
                'sex' => $patient->sex,
                'address' => $patient->address,
                'occupation' => $patient->occ,
                'telephone' => $patient->tel,
                'nic' => $patient->nic,
                'age' => Patients::find($patient->id)->getAge(),
                'id' => $patient->id,
            ]);
        } else { //if patient registration number have been given
            return response()->json([
                'exist' => false,
            ]);
        }
        }

        else
        {

        $patient = DB::table('patients')
                        ->join('appointments', 'patients.id', '=', 'appointments.patient_id')
                        ->select('patients.id as id', 'patients.name as name', 'patients.sex as sex', 'patients.address as address', 'patients.occupation as occ', 'patients.telephone as tel', 'patients.nic as nic', 'appointments.admit as ad', 'patients.bod as bod','appointments.number as appnum','appointments.doctor_id as D1')
                        ->whereRaw(DB::Raw("appointments.admit='YES' and patients.id='$pNum'"))
                        ->first();
        if ($patient) {

            return response()->json([
                'exist' => true,
                'name' => $patient->name,
                'sex' => $patient->sex,
                'address' => $patient->address,
                'occupation' => $patient->occ,
                'telephone' => $patient->tel,
                'nic' => $patient->nic,
                'age' => Patients::find($patient->id)->getAge(),
                'id' => $patient->id,
            ]);
        } else {
            return response()->json([
                'exist' => false,
            ]);
        }
    }
}

    public function store_inpatient(Request $request)
    {
        $pid = $request->reg_pid;
        $Ptable = Patients::find($pid);
        $INPtable = new inpatient;

        $Ptable->civil_status = $request->reg_ipcondition;
        $Ptable->birth_place = $request->reg_ipbirthplace;
        $Ptable->nationality = $request->reg_ipnation;
        $Ptable->religion = $request->reg_ipreligion;
        $Ptable->income = $request->reg_inpincome;
        $Ptable->guardian = $request->reg_ipguardname;
        $Ptable->guardian_address = $request->reg_ipguardaddress;

        $INPtable->patient_id = $request->reg_pid;
        $INPtable->ward_id = $request->reg_ipwardno;
        $INPtable->patient_inventory = $request->reg_ipinventory;

        $INPtable->house_doctor = $request->reg_iphousedoc;
        $INPtable->approved_doctor = $request->reg_ipapprovedoc;
        $INPtable->disease = $request->reg_admitofficer1;
        $INPtable->duration = $request->reg_admitofficer2;
        $INPtable->condition = $request->reg_admitofficer3;
        $INPtable->certified_officer = $request->reg_admitofficer4;

        $Ptable->save();
        $INPtable->save();

        // decrement bed count by 1
        $getFB = Ward::where('ward_no', $request->reg_ipwardno)->first();
        $newFB = $getFB->free_beds-=1;
        Ward::where('ward_no', $request->reg_ipwardno)->update(['free_beds' => $newFB]);


        return redirect()->back()->with('regpsuccess', "Inpatient Successfully Registered");
    }

    public function get_ward_list()
    {
        $wardList = $this->wardList;
        $data=DB::table('wards')->join('users','wards.doctor_id','=','users.id')->select('*')->get();
         return view('register_in_patient_view', ['data'=>$data]);
        // $wards = Ward::all();
        // dd($wardss);
        // return view('register_in_patient_view', compact(['wards']));
    }

    public function discharge_inpatient()
    {
        $user = Auth::user();
        return view('patient.discharge_inpatient_view', ['title' => "Discharge Inpatient"]);
    }

    public function disInPatientValid(Request $request)
    {
        $pNum = $request->pNum;
        $inpatient = DB::table('patients')
                        ->join('inpatients', 'patients.id', '=', 'inpatients.patient_id')
                        ->select('inpatients.patient_id as id', 'patients.name as name', 'patients.address as address', 'patients.telephone as tel', 'inpatients.discharged as dis')
                        ->whereRaw(DB::Raw("inpatients.patient_id='$pNum' and inpatients.discharged='NO'"))
                        ->first();

        if ($inpatient) {

            return response()->json([
                'exist' => true,
                'name' => $inpatient->name,
                'address' => $inpatient->address,
                'telephone' => $inpatient->tel,
                'id' => $inpatient->id,
            ]);
        } else {
            return response()->json([
                'exist' => false,
            ]);
        }
    }

    public function store_disinpatient(Request $request)
    {
        // try{
        $pid = $request->reg_pid;
        $INPtableUpdate = Inpatient::where('patient_id', $pid)->first();

        $timestamp = now();
        $INPtableUpdate->discharged = 'YES';
        $INPtableUpdate->discharged_date = $timestamp;
        $INPtableUpdate->description = $request->reg_medicalofficer1;
        $INPtableUpdate->discharged_officer = $request->reg_medicalofficer2;

        $INPtableUpdate->save();

        // increment bed count by 1
        $wardNo = $INPtableUpdate->ward_id;
        $getFB = Ward::where('ward_no', $wardNo)->first();
        $newFB = $getFB->free_beds+=1;
        Ward::where('ward_no', $wardNo)->update(['free_beds' => $newFB]);

        return view('patient.discharge_recipt',compact('INPtableUpdate'))->with('regpsuccess', "Inpatient Successfully Discharged");;
        // }
        // catch(\Throwable $th){
        //     return redirect()->back()->with('error',"Unkown Error Occured");
        // }
    }

    public function getPatientData(Request $request)
    {
        $regNum = $request->regNum;
        $patient = Patients::find($regNum);
        if ($patient) {

            $num = DB::table('appointments')->select('id')->whereRaw(DB::raw("date(created_at)=CURDATE()"))->count() + 1;

            return response()->json([
                'exist' => true,
                'name' => $patient->name,
                'sex' => $patient->sex,
                'address' => $patient->address,
                'occupation' => $patient->occupation,
                'telephone' => $patient->telephone,
                'nic' => $patient->nic,
                'age' => $patient->getAge(),
                'id' => $patient->id,
                'appNum' => $num,
            ]);
        } else {
            return response()->json([
                'exist' => false,
            ]);
        }
    }
public function addChannel(Request $request)
    {
        $app = new Appointment;
        $num = DB::table('appointments')->select('id')->whereRaw(DB::raw("date(created_at)=CURDATE()"))->count() + 1;
        $pid = $request->id;
        $patient = Patients::find($pid);

        $app->number = $num;
        $app->patient_id = $pid;
        $app->save();
        try {
            $app->save();
            return response()->json([
                'exist' => true,
                'name' => $patient->name,
                'id' => $patient->id,
                'appID' => $app->id,
                'appNum' => $num,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'exist' => false,
            ]);
        }
    }

    public function editPatientview(Request $request)
    {
        // dd($request->reg_pid);
        $user = Auth::user();
        // $data = DB::table('patients')->select('*')->where('id',$request->reg_pid)->first();
        $data = Patients::find($request->reg_pid);
        return view('patient.edit_patient_view', ['title' => "Edit Patient", 'patient' => $data]);
    }

    public function updatePatient(Request $result)
    {
        // dd($result->reg_pbd);
        $user = Auth::user();

        $query = DB::table('patients')
            ->where('id', $result->reg_pid)
            ->update(array(
                'name' => $result->reg_pname,
                'address' => $result->reg_paddress,
                'sex' => $result->reg_psex,
                'bod' => $result->reg_pbd,
                'occupation' => $result->reg_poccupation,
                'nic' => $result->reg_pnic,
                'telephone' => $result->reg_ptel,
            ));

        if ($query) {
            //activity log
            activity()->performedOn($user)->log('Patient details updated!');
            return redirect()
                ->route('searchPatient')
                ->with('success', 'You have successfully updated patient details.');
        } else {
            return redirect()
                ->route('searchPatient')
                ->with('unsuccess', 'Error in Updating details !!!');
        }

    }
}
