<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCasTicketsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_ticket_granting_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('tgt')->unique();
            $table->timestamp('expire_at');
            $table->timestamps();
        });

        Schema::create('cas_service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('st')->unique();
            $table->string('tgt');
            $table->integer('client_id');
            $table->timestamp('expire_at');
            $table->timestamps();
        });

        Schema::create('cas_proxy_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('pt')->unique();
            $table->string('pgt');
            $table->integer('client_id');
            $table->timestamp('expire_at');
            $table->timestamps();
        });

        Schema::create('cas_proxy_granting_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('pgt');
            $table->string('tgt')->unique();
            $table->timestamp('expire_at');
            $table->timestamps();
        });

        Schema::create('cas_ticket_validation_records', function (Blueprint $table) {
            $table->id();
            $table->string('ticket');
            $table->integer('client_id');
            $table->timestamp('validated_at');
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
        Schema::dropIfExists('cas_ticket_granting_tickets');
        Schema::dropIfExists('cas_service_tickets');
        Schema::dropIfExists('cas_proxy_tickets');
        Schema::dropIfExists('cas_proxy_granting_tickets');
        Schema::dropIfExists('cas_ticket_validation_records');
    }
}
