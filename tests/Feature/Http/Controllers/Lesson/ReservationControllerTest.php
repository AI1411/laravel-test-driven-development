<?php

namespace Tests\Feature\Http\Controllers\Lesson;

use App\Models\Lesson;
use App\Models\Reservation;
use App\Notifications\ReservationCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\Traits\CreateUser;
use Tests\TestCase;

class ReservationControllerTest extends TestCase
{
    use RefreshDatabase, CreateUser;

    /**
     * @test
     */
    public function testInvokeValid()
    {
        Notification::fake();

        $lesson = factory(Lesson::class)->create();
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->post("/lessons/{$lesson->id}/reserve");
        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect("/lessons/{$lesson->id}");

        $this->assertDatabaseHas('reservations', [
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
        ]);

        Notification::assertSentTo(
            $user,
            ReservationCompleted::class,
            function (ReservationCompleted $notification) use ($lesson) {
                return $notification->lesson->id === $lesson->id;
            }
        );
    }

    /**
     * @test
     */
    public function testInvokeInvalid()
    {
        Notification::fake();

        $lesson = factory(Lesson::class)->create(['capacity' => 1]);
        $anotherUser = $this->createUser();
        $lesson->reservations()->save(factory(Reservation::class)->make(['user_id' => $anotherUser->id]));

        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->from("/lessons/{$lesson->id}")
            ->post("/lessons/{$lesson->id}/reserve");

        $response->assertStatus(Response::HTTP_FOUND);
        $response->assertRedirect("/lessons/{$lesson->id}");
        $response->assertSessionHasErrors();

        $error = session('errors')->first();
        $this->assertStringContainsString('予約できません。', $error);

        $this->assertDatabaseMissing('reservations', [
            'lesson_id' => $lesson->id,
            'user_id' => $user->id,
        ]);

       Notification::assertNotSentTo($user, ReservationCompleted::class);
    }
}
