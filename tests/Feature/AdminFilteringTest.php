<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReportController;
use App\Models\Comment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_filters_by_role_status_and_date_range(): void
    {
        Schema::create('users', function ($table) {
            $table->id('user_id');
            $table->string('username');
            $table->string('email');
            $table->string('role')->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();
        });

        $admin = User::create([
            'username' => 'admin1',
            'email' => 'admin1@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $admin->created_at = '2024-01-10 10:00:00';
        $admin->save();

        $editor = User::create([
            'username' => 'editor1',
            'email' => 'editor1@example.com',
            'role' => 'editor',
            'is_active' => false,
        ]);
        $editor->created_at = '2024-02-10 10:00:00';
        $editor->save();

        $request = new Request([
            'page' => 1,
            'role' => 'admin',
            'status' => 'active',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
        ]);

        $controller = new AdminController();
        $response = $controller->getUsers($request);
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('admin', $data['data'][0]['role']);
        $this->assertTrue($data['data'][0]['is_active']);
    }

    public function test_admin_reports_filters_by_search_and_date_range(): void
    {
        Schema::create('users', function ($table) {
            $table->id('user_id');
            $table->string('username');
            $table->string('email');
            $table->string('role')->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('facebook_id')->nullable();
            $table->string('google_id')->nullable();
        });

        Schema::create('comments', function ($table) {
            $table->id('comment_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('content');
            $table->unsignedBigInteger('news_id')->nullable();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function ($table) {
            $table->id('report_id');
            $table->unsignedBigInteger('reporter_id');
            $table->unsignedBigInteger('comment_id');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('action_taken')->nullable();
            $table->integer('ban_duration_days')->nullable();
            $table->timestamp('ban_until')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        $reporter = User::create([
            'username' => 'reporter',
            'email' => 'reporter@example.com',
            'role' => 'user',
            'is_active' => true,
        ]);
        $reporter->created_at = now();
        $reporter->save();

        $commenter = User::create([
            'username' => 'commenter',
            'email' => 'commenter@example.com',
            'role' => 'user',
            'is_active' => true,
        ]);
        $commenter->created_at = now();
        $commenter->save();

        $comment = Comment::create([
            'user_id' => $commenter->user_id,
            'content' => 'Spam content to filter',
            'news_id' => 1,
            'post_id' => null,
        ]);
        $comment->created_at = '2024-01-15 09:00:00';
        $comment->updated_at = '2024-01-15 09:00:00';
        $comment->save();

        $report = Report::create([
            'reporter_id' => $reporter->user_id,
            'comment_id' => $comment->comment_id,
            'reason' => 'spam',
            'description' => 'Test report',
            'status' => 'pending',
        ]);
        $report->created_at = '2024-01-15 09:00:00';
        $report->updated_at = '2024-01-15 09:00:00';
        $report->save();

        $request = new Request([
            'page' => 1,
            'search' => 'filter',
            'date_from' => '2024-01-01',
            'date_to' => '2024-01-31',
            'status' => 'pending',
            'reason' => 'all',
        ]);

        $controller = new ReportController();
        $response = $controller->index($request);
        $data = $response->getData(true);

        $this->assertCount(1, $data['data']);
        $this->assertSame('spam', $data['data'][0]['reason']);
    }
}
