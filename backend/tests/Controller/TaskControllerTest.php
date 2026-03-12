<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TaskControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(): array
    {
        $client = static::createClient();
        $email = 'task_test_' . uniqid() . '@example.com';

        // Register
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        // Login
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => self::$test_password,
        ]) ?: '');

        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $token = $response_data['token'] ?? '';

        return ['client' => $client, 'token' => $token, 'email' => $email];
    }

    public function test_create_task(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Task ' . uniqid(),
            'description' => 'This is a test task description',
            'status' => 'todo',
            'priority' => 'high',
            'dueDate' => '2026-12-31 23:59:59',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertArrayHasKey('description', $response_data);
        $this->assertArrayHasKey('status', $response_data);
        $this->assertArrayHasKey('priority', $response_data);
        $this->assertEquals('todo', $response_data['status']);
        $this->assertEquals('high', $response_data['priority']);
    }

    public function test_create_task_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/task', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Task',
            'description' => 'This is a test task',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_task_with_invalid_data(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Test with title too short (less than 3 characters)
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'AB',
            'description' => 'This is a test task',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_fetch_user_tasks(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a task first
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Task ' . uniqid(),
            'description' => 'This is a test task',
            'status' => 'todo',
            'priority' => 'medium',
        ]) ?: '');

        // Fetch tasks
        $client->request('GET', '/api/task/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertIsArray($response_data);
        $this->assertGreaterThan(0, count($response_data));
    }

    public function test_fetch_tasks_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/task/');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_single_task(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a task first
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Single Task ' . uniqid(),
            'description' => 'Description for single task',
            'status' => 'todo',
            'priority' => 'low',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $task_id = $create_response['id'];

        // Get the task
        $client->request('GET', '/api/task/' . $task_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertArrayHasKey('description', $response_data);
        $this->assertArrayHasKey('status', $response_data);
        $this->assertArrayHasKey('priority', $response_data);
        $this->assertEquals($task_id, $response_data['id']);
    }

    public function test_get_single_task_not_found(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Try to get a non-existent task
        $client->request('GET', '/api/task/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Task not found', $response_data['error']);
    }

    public function test_update_task(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a task first
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Original Task ' . uniqid(),
            'description' => 'Original description',
            'status' => 'todo',
            'priority' => 'low',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $task_id = $create_response['id'];

        // Update the task
        $client->request('PUT', '/api/task/' . $task_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Updated Task Title',
            'description' => 'Updated description',
            'status' => 'in_progress',
            'priority' => 'high',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertEquals('Updated Task Title', $response_data['title']);
        $this->assertEquals('Updated description', $response_data['description']);
        $this->assertEquals('in_progress', $response_data['status']);
        $this->assertEquals('high', $response_data['priority']);
    }

    public function test_update_task_not_found(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Try to update a non-existent task
        $client->request('PUT', '/api/task/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Updated Title',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Task not found', $response_data['error']);
    }

    public function test_delete_task(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a task first
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Task to Delete ' . uniqid(),
            'description' => 'This task will be deleted',
            'status' => 'todo',
            'priority' => 'medium',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $task_id = $create_response['id'];

        // Delete the task
        $client->request('DELETE', '/api/task/' . $task_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_task_not_found(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Try to delete a non-existent task
        $client->request('DELETE', '/api/task/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Task not found', $response_data['error']);
    }

    public function test_toggle_task_status(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $task_title = 'Task to Toggle ' . uniqid();

        // Create a task with TODO status
        $client->request('POST', '/api/task', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => $task_title,
            'description' => 'This task will be toggled',
            'status' => 'todo',
            'priority' => 'medium',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $task_id = $create_response['id'];

        // Toggle to DONE (must include title due to DTO validation)
        $client->request('PUT', '/api/task/' . $task_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => $task_title,
            'description' => 'This task will be toggled',
            'status' => 'done',
            'priority' => 'medium',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertEquals('done', $response_data['status']);

        // Toggle back to TODO
        $client->request('PUT', '/api/task/' . $task_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => $task_title,
            'description' => 'This task will be toggled',
            'status' => 'todo',
            'priority' => 'medium',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertEquals('todo', $response_data['status']);
    }
}
