<?php

declare(strict_types=1);

/*
 * "Chia sẻ" từ app khác vào /gui-file (PWA share target). Service worker xử lý trên máy; request chỉ
 * tới server khi service worker chưa chạy.
 */

test('the manifest lets Android share files into the transfer page', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

    expect($manifest['share_target'])->toMatchArray([
        'action' => '/gui-file/chia-se',
        'method' => 'POST',
        'enctype' => 'multipart/form-data',
    ])->and($manifest['share_target']['params']['files'][0]['name'])->toBe('files');
});

test('a share that reaches the server goes back to the page with a notice, without CSRF errors or storing the file', function () {
    $this->post(route('transfer.share'), ['text' => 'xin chào'])
        ->assertStatus(303)
        ->assertRedirect(route('transfer.show', ['chia-se' => 'loi']));
});
