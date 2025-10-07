<?php

namespace App\DTOs;

use App\Models\Message;

class ChatDTO
{
	public function __construct(
		public readonly int $id,
		public readonly int $sender_id,
		public readonly int $receiver_id,
		public readonly string $message,
		public readonly ?string $read_at,
		public readonly string $created_at,
		public readonly string $updated_at,
		public readonly ?array $sender_info = null,
		public readonly ?array $receiver_info = null,
	) {}

	/**
	 * Create a ChatDTO from a Message model instance.
	 *
	 * @param Message $message
	 * @return self
	 */
	public static function fromModel(Message $message): self
	{
		return new self(
			id: $message->id,
			sender_id: $message->sender_id,
			receiver_id: $message->receiver_id,
			message: $message->message,
			read_at: $message->read_at ? $message->read_at->toISOString() : null,
			created_at: is_string($message->created_at) ? $message->created_at : $message->created_at->toISOString(),
			updated_at: is_string($message->updated_at) ? $message->updated_at : $message->updated_at->toISOString(),
			sender_info: $message->sender ? [
				'id' => $message->sender->id,
				'name' => $message->sender->name,
				'email' => $message->sender->email
			] : null,
			receiver_info: $message->receiver ? [
				'id' => $message->receiver->id,
				'name' => $message->receiver->name,
				'email' => $message->receiver->email
			] : null
		);
	}

	/**
	 * Convert ChatDTO to array.
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'sender_id' => $this->sender_id,
			'receiver_id' => $this->receiver_id,
			'message' => $this->message,
			'read_at' => $this->read_at,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'sender_info' => $this->sender_info,
			'receiver_info' => $this->receiver_info,
		];
	}
}
