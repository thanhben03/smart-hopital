@php use Illuminate\Support\Facades\DB; @endphp
@extends('template.patient')

@section('content')
    <div style="display: none" class="loading">
        <img src="{{asset('/icons/spinner.svg')}}" alt="">
    </div>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Đăng Ký Khám Bệnh</h2>
        <form id="formSubmit">
            @csrf
            <div class="row">
                <div class="col mb-3">
                    <label for="fullName" class="form-label">Họ và tên</label>
                    <input type="text" name="fullname" class="form-control" id="fullname" placeholder="Nhập họ và tên"
                           required>
                </div>
                <div class="col mb-3">
                    <label for="age" class="form-label">Ngày sinh</label>
                    <input type="date" name="birthday" class="form-control" id="birthday" placeholder="dd-MM-YYYY"
                           required>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label for="gender" class="form-label">Giới tính</label>
                    <select name="gender" class="form-select" id="gender" required>
                        <option selected disabled value="">Chọn giới tính</option>
                        <option id value="Nam">Nam</option>
                        <option id value="Nữ">Nữ</option>
                        <option value="other">Khác</option>
                    </select>
                </div>
                <div class="col mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="tel" name="phone" class="form-control" id="phone" placeholder="Nhập số điện thoại"
                           required>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" id="email" placeholder="Nhập email">
                </div>
                <div class="col mb-3">
                    <label for="appointmentDate" class="form-label">Chọn khoa khám</label>
                    <select name="department" class="form-select" id="appointmentDate" required>
                        @foreach(DB::table('departments')->get() as $item)
                            <option value="{{$item->id}}">{{$item->department_name}}</option>
                        @endforeach

                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col mb-3">
                    <label for="appointmentType" class="form-label">Địa chỉ</label>
                    <input type="text" name="address" id="address" class="form-control" placeholder="Nhập địa chỉ">
                </div>
                <div class="col mb-3">
                    <label for="appointmentType" class="form-label">Số CCCD</label>
                    <input type="text" name="cccd" id="cccd-number" class="form-control" placeholder="Nhập số CCCD">
                </div>
            </div>
            <div class="mb-3">
                <textarea name="trieu_chung" class="form-control" rows="6" placeholder="Triệu chứng"></textarea>
            </div>
            <div class="d-flex">
                <button type="submit" class="btn btn-primary">Đăng ký</button>
                <button onclick="scan()" class="mx-2 btn btn-success">Scan CCCD</button>
            </div>
        </form>
    </div>
@endsection

@push('js')
    <script>
        function scan() {
            $.ajax({
                type: "POST",
                url: "/scan-cccd",
                data: {
                    "_token": "{{csrf_token()}}"
                },
                beforeSend: function () {
                    $(".loading").css('display', 'flex')
                },
                success: function (res) {
                    $("#fullname").val(res.bn_name)
                    $("#birthday").val(res.dob)
                    $("#address").val(res.birthplace)
                    $("#cccd-number").val(res.cccd)
                    $("#gender").val(`${res.gender == 'Nam' ? 'Nam' : 'Nữ'}`)
                    $(".loading").css('display', 'none')

                },
                error: function (xhr) {
                    console.log(xhr.responseJSON)
                }
            })
        }

        document.getElementById("formSubmit").addEventListener("submit", function (event) {
            event.preventDefault()
            register()
        });

        function register() {
            let form = $("#formSubmit").serialize();

            $.ajax({
                type: 'POST',
                url: '/register',
                data: form,
                success: function (res) {
                    alert('ok')
                    window.location.reload()
                },
                error: function (xhr) {
                    console.log(xhr.responseJSON)
                }
            })
        }
    </script>
@endpush
