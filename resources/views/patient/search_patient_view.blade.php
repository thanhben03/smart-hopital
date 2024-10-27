@php use Illuminate\Support\Facades\DB; @endphp
@extends('template.main')

@section('title', $title)

@section('content_title',__("Danh Sách Chờ"))
@section('content_description',__("Search,View & Update Patient Details"))
@section('breadcrumbs')
    <ol class="breadcrumb">
        <li><a href="#"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
        <li class="active">Here</li>
    </ol>

@endsection

@section('main_content')
    <input type="text" hidden id="current-stt">
    <!-- The modal chuyen khoa -->
    <div class="modal fade" id="modal-next-department" tabindex="-1" role="dialog" aria-labelledby="modalLabel"
         aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="modalLabel">Chuyển Khoa</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Chọn khoa: </label>
                        <select class="form-control" name="" id="department_id">
                            @foreach(DB::table('departments')->get() as $item)
                                <option value="{{$item->id}}">{{$item->department_name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ghi Chú: </label>
                        <textarea class="form-control" id="trieu_chung"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="nextDepartment()" type="button" class="btn btn-primary" >Chuyển khoa</button>
                    <button onclick="done()" type="button" class="btn btn-success" data-dismiss="modal">Kết Thúc</button>
                </div>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-md-1"></div>
        <div class="col-md-10">
            <form action={{route('searchData')}} method="GET" role="search">
                @csrf

                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('unsuccess'))
                    <div class="alert alert-danger">
                        {{ session('unsuccess') }}
                    </div>
                @endif
                <div class="callout callout-info">
                    <label class="h4">{{__('Search Patient With ...')}}</label>
                    <div class="row">
                        <div class="col-md-1"></div>
                        <div class="col-md-5">

                            <label class="mr-2">
                                <input onchange="changeFunc('Name');" style="display:inline-block" checked type="radio"
                                       name="cat" id="cat" value="name">
                                {{__('Name')}}
                            </label>


                            <label class="ml-2 mr-4">
                                <input onchange="changeFunc('Telephone Number');" style="display:inline-block"
                                       type="radio"
                                       name="cat" id="cat" value="telephone">
                                {{__('Telephone')}}
                            </label>


                            <label>
                                <input onchange="changeFunc('NIC Number');" style="display:inline-block" type="radio"
                                       name="cat" id="cat" value="nic">
                                {{__('NIC Number')}}
                            </label>
                        </div>
                        <div class="col-md-1"></div>
                    </div>
                    <script>
                        function changeFunc(txt) {
                            document.getElementById("keyword").placeholder = "Enter Patient " + txt;
                        }
                    </script>
                    <div class="row">
                        <div class="col-md-1"></div>
                        <div class="col-md-10">
                            <div class="input-group">
                                <input required type="text" value="{{$old_keyword}}" class="form-control" id="keyword"
                                       name="keyword"
                                       placeholder="Enter Patient">
                                <span class="input-group-btn">
                                <button type="submit" class="btn btn-default">
                                    <span class="glyphicon glyphicon-search"></span>
                                </button>
                            </span>

                            </div>

                            @if(request()->get('cat'))
                                <a href="/search" class="btn btn-warning mt-3">Quay lại</a>

                            @endif

                        </div>

                        <div class="col-md-1"></div>
                    </div>

                </div>
        </div>
        <div class="col-md-1"></div>
    </div>

    </form>

    @if($search_result)
        @if(count($search_result) > 0)

            @foreach($search_result as $patient)
                {{-- Search Results --}}
                <div class="row" id="element-{{$patient['stt']}}">
                    <!-- right column -->
                    <div class="col-md-1"></div>
                    <div class="col-md-10">
                        <!-- Horizontal Form -->
                        <div class="box box-info">
                            <div class="box-header with-border"
                                 @if($patient['stt'] == $currentPatient->stt) style="background: #ffcccc;" @endif>
                                <h3 class="box-title">{{__('STT Khám Bệnh: #'.$patient['stt'])}}
                                    - {{$patient['info']->name}}</h3>
                                <a class="btn btn-primary" type="button" data-toggle="collapse"
                                   data-target="#patientInfo-{{$patient['stt']}}" aria-expanded="false"
                                   aria-controls="patientInfo">
                                    Xem chi tiết
                                </a>
                                @if($patient['stt'] == $currentPatient->stt)
                                    <button class="btn btn-danger">Đang tới lượt</button>
                                    <button onclick="step1({{$patient['stt']}})" type="button" class="btn btn-success"
                                            style="float: right">{{__('Hoàn Thành')}}</button>

                                @endif
                            </div>

                            <div class="collapse" id="patientInfo-{{$patient['stt']}}">
                                <!-- Nav tabs -->
                                <ul class="nav nav-tabs" role="tablist">
                                    <li role="presentation" class="active">
                                        <a href="#info-{{$patient['stt']}}" aria-controls="info" role="tab"
                                           data-toggle="tab">{{__('Thông Tin Bệnh Nhân')}}</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#diagnosis-{{$patient['stt']}}" aria-controls="diagnosis" role="tab"
                                           data-toggle="tab">{{__('Chuẩn Đoán')}}</a>
                                    </li>
                                    <li role="presentation">
                                        <a href="#history-{{$patient['stt']}}" aria-controls="history" role="tab"
                                           data-toggle="tab">{{__('Lịch Sử Khám Bệnh')}}</a>
                                    </li>
                                </ul>

                                <!-- Tab panes -->
                                <div class="tab-content">
                                    <!-- Tab 1: Thông tin bệnh nhân -->
                                    <div role="tabpanel" class="tab-pane active" id="info-{{$patient['stt']}}">
                                        <form class="form-horizontal" action="{{route('editpatient')}}" method="POST">
                                            @csrf
                                            <div class="box-body">
                                                <div class="form-group">
                                                    <label for="inputEmail3"
                                                           class="col-sm-2 control-label">{{__('Patient ID')}}</label>
                                                    <div class="col-sm-10">
                                                        <input readonly value="{{$patient['id']}}" type="text" required
                                                               class="form-control" name="reg_pname">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="inputEmail3"
                                                           class="col-sm-2 control-label">{{__('Full Name')}}</label>
                                                    <div class="col-sm-10">
                                                        <input readonly value="{{$patient['name']}}" type="text"
                                                               required class="form-control" name="reg_pname"
                                                               placeholder="Enter Patient Full Name">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="inputEmail3"
                                                           class="col-sm-2 control-label">{{__('NIC Number')}}</label>
                                                    <div class="col-sm-10">
                                                        <input readonly value="{{$patient['nic']}}" type="text" required
                                                               class="form-control" name="reg_pnic"
                                                               placeholder="National Identity Card Number">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="inputPassword3"
                                                           class="col-sm-2 control-label">{{__('Address')}}</label>
                                                    <div class="col-sm-10">
                                                        <input readonly type="text" value="{{$patient['address']}}"
                                                               required class="form-control" name="reg_paddress"
                                                               placeholder="Enter Patient Address ">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="inputPassword3"
                                                           class="col-sm-2 control-label">{{__('Telephone')}}</label>
                                                    <div class="col-sm-10">
                                                        <input readonly value="{{$patient['telephone']}}" type="tel"
                                                               class="form-control" name="reg_ptel"
                                                               placeholder="Patient Telephone Number">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="col-sm-2 control-label">{{__('Sex')}}</label>
                                                    <div class="col-sm-2">
                                                        <input readonly value="{{$patient['sex']}}" type="text" required
                                                               class="form-control" name="reg_poccupation"
                                                               placeholder="Enter Patient Occupation ">
                                                    </div>

                                                    <label class="col-sm-2 control-label">{{__('DOB')}}</label>
                                                    <div class="col-sm-3">
                                                        <div class="input-group date">
                                                            <div class="input-group-addon">
                                                                <i class="fa fa-calendar"></i>
                                                            </div>
                                                            <input readonly value="{{$patient['bod']}}" type="text"
                                                                   class="form-control pull-right" name="reg_pbd"
                                                                   placeholder="Birthday">
                                                            <input readonly value="{{$patient['id']}}" type="text"
                                                                   class="form-control pull-right" name="reg_pid"
                                                                   style="display:none">
                                                        </div>
                                                    </div>

                                                    <div class="col-sm-3">
                                                        <div class="btn-group pull-right" role="group"
                                                             aria-label="Button group">
                                                            <button type="button" onclick="go('{{$patient['id']}}')"
                                                                    class="btn bg-navy"><i
                                                                    class="far fa-id-card"></i> {{__('Profile')}}
                                                            </button>
                                                            <button class="btn btn-warning"><i
                                                                    class="fas fa-edit"></i> {{__('Edit')}}</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Tab 2: Chuẩn đoán -->
                                    <div role="tabpanel" class="tab-pane" id="diagnosis-{{$patient['stt']}}">
                                        <form class="form-horizontal" action="" method="POST">
                                            @csrf
                                            <div class="box-body">
                                                <!-- Triệu chứng -->
                                                <div class="form-group">
                                                    <label for="symptoms"
                                                           class="col-sm-2 control-label">{{__('Triệu Chứng')}}</label>
                                                    <div class="col-sm-10">
                                                        <textarea class="form-control" name="symptoms" rows="3"
                                                                  placeholder="Nhập triệu chứng..."></textarea>
                                                    </div>
                                                </div>

                                                <!-- Chuẩn đoán -->
                                                <div class="form-group">
                                                    <label for="diagnosis"
                                                           class="col-sm-2 control-label">{{__('Chuẩn Đoán')}}</label>
                                                    <div class="col-sm-10">
                                                        <textarea class="form-control" name="diagnosis" rows="3"
                                                                  placeholder="Nhập chuẩn đoán..."></textarea>
                                                    </div>
                                                </div>

                                                <!-- Kê đơn thuốc -->
                                                <div class="form-group">
                                                    <label for="prescription"
                                                           class="col-sm-2 control-label">{{__('Kê Đơn Thuốc')}}</label>
                                                    <div class="col-sm-10">
                                                        <table class="table table-bordered" id="prescriptionTable">
                                                            <thead>
                                                            <tr>
                                                                <th>{{__('Tên Thuốc')}}</th>
                                                                <th>{{__('Số Lượng')}}</th>
                                                                <th>{{__('Cách Dùng')}}</th>
                                                                <th></th> <!-- Cột để xóa hàng -->
                                                            </tr>
                                                            </thead>
                                                            <tbody>
                                                            <tr>
                                                                <td><input type="text" name="medicine_name[]"
                                                                           class="form-control"
                                                                           placeholder="Nhập tên thuốc..."></td>
                                                                <td><input type="number" name="medicine_quantity[]"
                                                                           class="form-control"
                                                                           placeholder="Nhập số lượng..."></td>
                                                                <td><input type="text" name="medicine_usage[]"
                                                                           class="form-control"
                                                                           placeholder="Nhập cách dùng..."></td>
                                                                <td>
                                                                    <button type="button"
                                                                            class="btn btn-danger removeRow">{{__('Xóa')}}</button>
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                        <button type="button" class="btn btn-primary"
                                                                id="addRow">{{__('Thêm Thuốc')}}</button>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <div class="col-sm-offset-2 col-sm-10">
                                                        <button type="submit"
                                                                class="btn btn-success">{{__('Lưu Chuẩn Đoán')}}</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <!-- Tab 3: Lịch sử khám bệnh -->
                                    <div role="tabpanel" class="tab-pane" id="history-{{$patient['stt']}}">
                                        <div class="box-body">
                                            <h4>{{__('Lịch sử khám bệnh của bệnh nhân')}}</h4>
                                            <table class="table table-bordered">
                                                <thead>
                                                <tr>
                                                    <th>{{__('Ngày Khám')}}</th>
                                                    <th>{{__('Chuẩn Đoán')}}</th>
                                                    <th>{{__('Kê Đơn Thuốc')}}</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <!-- Dữ liệu ví dụ, có thể thay bằng dữ liệu từ cơ sở dữ liệu -->
                                                {{--                                @foreach($patient['history'] as $history)--}}
                                                {{--                                    <tr>--}}
                                                {{--                                        <td>{{$history['date']}}</td>--}}
                                                {{--                                        <td>{{$history['diagnosis']}}</td>--}}
                                                {{--                                        <td>{{$history['prescription']}}</td>--}}
                                                {{--                                    </tr>--}}
                                                {{--                                @endforeach--}}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>

                    <div class="col-md-1"></div>
                </div>
            @endforeach
            <script>
                function go(pid) {
                    window.location.href = "/patient/" + pid;
                }

                $(document).ready(function () {
                    // Thêm hàng mới vào bảng khi nút "Thêm Thuốc" được nhấn
                    $('#addRow').click(function () {
                        var newRow = `
            <tr>
                <td><input type="text" name="medicine_name[]" class="form-control" placeholder="Nhập tên thuốc..."></td>
                <td><input type="number" name="medicine_quantity[]" class="form-control" placeholder="Nhập số lượng..."></td>
                <td><input type="text" name="medicine_usage[]" class="form-control" placeholder="Nhập cách dùng..."></td>
                <td><button type="button" class="btn btn-danger removeRow">{{__('Xóa')}}</button></td>
            </tr>`;
                        $('#prescriptionTable tbody').append(newRow);
                    });

                    // Xóa hàng khi nhấn nút "Xóa"
                    $(document).on('click', '.removeRow', function () {
                        $(this).closest('tr').remove();
                    });


                });

                function step1(stt) {
                    $("#modal-next-department").modal('toggle')
                    $("#current-stt").val(stt);

                }

                function done() {
                    let stt = $("#current-stt").val();

                    $.ajax({
                        type: "GET",
                        url: "{{route('patient.done', ':stt')}}".replace(':stt', stt),
                        success: function (res) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1200)
                        }
                    })
                }

                function nextDepartment() {
                    let stt = $("#current-stt")
                    let trieu_chung = $("#trieu_chung")
                    let department_id = $("#department_id")
                    $.ajax({
                        type: "POST",
                        url: "/next-department",
                        data: {
                            stt: stt.val(),
                            trieu_chung: trieu_chung.val(),
                            department_id: department_id.val(),
                            "_token": "{{csrf_token()}}"
                        },
                        success: function (res) {

                        }
                    })
                }

            </script>
        @else
            <div class="row">
                <div class="col-md-1"></div>
                <div class="col-md-10">
                    <h4>{{__('No results found...')}}</h4>
                </div>
                <div class="col-md-1"></div>
            </div>

        @endif
    @endif

@endsection
