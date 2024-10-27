<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePatientVisitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patient_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->string('stt');
            $table->string('trieu_chung')->nullable();
            $table->string('chuan_doan')->nullable();
            $table->unsignedBigInteger('department_id');
            $table->foreign('department_id')->references('id')->on('departments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_visits');
    }
}
