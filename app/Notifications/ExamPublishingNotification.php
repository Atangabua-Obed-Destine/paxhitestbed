<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to students when exam results are published.
 * This notification appears in the student portal's notification bell icon.
 */
class ExamPublishingNotification extends Notification
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data Notification data containing:
     *   - id: unique identifier for this notification
     *   - title: notification title to display
     *   - message: detailed notification message
     *   - exam_type: type of exam (CA, Final, etc.)
     *   - subject_code: course code
     *   - subject_title: course title
     *   - publish_date: scheduled publish date
     *   - publish_time: scheduled publish time
     *   - type: notification type (exam_results)
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'id' => $this->data['id'] ?? 0,
            'title' => $this->data['title'] ?? 'Exam Results Published',
            'message' => $this->data['message'] ?? '',
            'exam_type' => $this->data['exam_type'] ?? '',
            'subject_code' => $this->data['subject_code'] ?? '',
            'subject_title' => $this->data['subject_title'] ?? '',
            'publish_date' => $this->data['publish_date'] ?? null,
            'publish_time' => $this->data['publish_time'] ?? null,
            'type' => 'exam_results',
        ];
    }
}
