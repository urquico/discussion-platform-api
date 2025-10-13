<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Protocol;
use App\Models\Thread;
use App\Models\Comment;
use App\Models\Review;
use App\Models\Vote;

/**
 * Seeds the application's database with realistic users, protocols, threads, comments, reviews, and votes.
 *
 * This seeder creates a complete dataset for development and testing, including:
 * - 5 users
 * - 12 wellness protocols (with realistic content)
 * - 24-36 threads (2-3 per protocol)
 * - 72-216 comments (with nested replies)
 * - 24-60 reviews (with ratings and feedback)
 * - Extensive voting data (threads and comments)
 *
 * All generated content uses English locale for consistency.
 *
 * @package Database\Seeders
 * @author Christian Bangay
 * @version 1.0.0
 * @since 2025-07-31
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Create admin user first
        $adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // Change this in production!
            'is_admin' => true,
        ]);

        // Create regular users
        $usersData = User::factory()->count(100)->make()->map(function ($user, $index) {
            return [
                'name' => $user->name,
                // the email is {n}@gmail.com where n is 1,2,3,4,5
                'email' => ($index + 1) . '@gmail.com',
                'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->format('Y-m-d H:i:s') : null,
                'password' => bcrypt('password'),
                'remember_token' => $user->remember_token,
                'is_admin' => false, // Regular users are not admin
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();
        User::insert($usersData);
        $users = User::all();

        // Create protocols with realistic wellness data - use actual user names as authors
        $protocols = [
            [
                'title' => 'Morning Detox Protocol',
                'content' => 'A comprehensive morning routine for detoxification including lemon water, green tea, and gentle stretching exercises.',
                'tags' => ['detox', 'morning', 'wellness'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Gut Health Restoration',
                'content' => 'Complete protocol for restoring gut health through probiotics, prebiotics, and elimination diet.',
                'tags' => ['gut-health', 'probiotics', 'digestion'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Sleep Optimization Protocol',
                'content' => 'Natural methods to improve sleep quality including blue light blocking, melatonin optimization, and sleep hygiene.',
                'tags' => ['sleep', 'melatonin', 'circadian-rhythm'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Stress Management & Cortisol Balance',
                'content' => 'Holistic approach to managing stress and balancing cortisol levels through meditation, exercise, and adaptogens.',
                'tags' => ['stress', 'cortisol', 'meditation'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Immune System Boost Protocol',
                'content' => 'Natural ways to strengthen the immune system with vitamins, herbs, and lifestyle modifications.',
                'tags' => ['immunity', 'vitamins', 'herbs'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Energy Optimization Protocol',
                'content' => 'Comprehensive approach to increasing energy levels through nutrition, exercise, and lifestyle changes.',
                'tags' => ['energy', 'nutrition', 'exercise'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Hormone Balance Protocol',
                'content' => 'Natural methods to balance hormones through diet, exercise, and stress management.',
                'tags' => ['hormones', 'balance', 'endocrine'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Brain Health & Cognitive Enhancement',
                'content' => 'Protocol for improving brain health and cognitive function through nutrition, exercise, and mental training.',
                'tags' => ['brain-health', 'cognitive', 'nootropics'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Inflammation Reduction Protocol',
                'content' => 'Anti-inflammatory diet and lifestyle changes to reduce chronic inflammation.',
                'tags' => ['inflammation', 'anti-inflammatory', 'diet'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Weight Management & Metabolism',
                'content' => 'Sustainable weight management through metabolic optimization and lifestyle changes.',
                'tags' => ['weight-loss', 'metabolism', 'nutrition'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Skin Health & Anti-Aging Protocol',
                'content' => 'Natural approaches to skin health and anti-aging through nutrition, skincare, and lifestyle.',
                'tags' => ['skin-health', 'anti-aging', 'collagen'],
                'author' => $users->random()->name,
            ],
            [
                'title' => 'Heart Health & Cardiovascular Support',
                'content' => 'Comprehensive protocol for heart health including diet, exercise, and stress management.',
                'tags' => ['heart-health', 'cardiovascular', 'exercise'],
                'author' => $users->random()->name,
            ],
        ];

        DB::beginTransaction();
        $createdProtocols = [];
        try {
            foreach ($protocols as $protocolData) {
                $protocol = Protocol::create($protocolData);
                $createdProtocols[] = $protocol;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // Create threads for each protocol
        $threadTitles = [
            'Has anyone tried this protocol?',
            'Looking for experiences with this approach',
            'Tips for beginners starting this protocol',
            'How long did it take to see results?',
            'Side effects and how to manage them',
            'Best time of day to follow this protocol',
            'Can this be combined with other protocols?',
            'Success stories and testimonials',
            'Modifications for different lifestyles',
            'Cost and accessibility considerations',
        ];
        $threadBodies = [
            'This protocol has been very effective for me. I noticed results after a few weeks and would recommend it to others.',
            'I had some doubts at first, but after following the steps, I saw significant improvements.',
            'Can anyone share their experience with this protocol? I am considering starting soon.',
            'I combined this protocol with my regular routine and it worked well.',
            'What are some tips for beginners? I want to make sure I do this right.',
            'I experienced some side effects initially, but they went away after a few days.',
            'How long did it take for you to see results?',
            'I appreciate the detailed instructions. Very easy to follow.',
            'Has anyone tried modifying this protocol for a different lifestyle?',
            'The cost is reasonable and the results are worth it.',
        ];

        $createdThreads = [];
        foreach ($createdProtocols as $protocol) {
            // Create 2-3 threads per protocol
            $threadCount = rand(2, 3);
            $threadData = [];
            for ($i = 0; $i < $threadCount; $i++) {
                $threadData[] = [
                    'protocol_id' => $protocol->getKey(),
                    'title' => $threadTitles[array_rand($threadTitles)],
                    'body' => $threadBodies[array_rand($threadBodies)],
                    'author' => $users->random()->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Thread::insert($threadData);
            $createdThreads = array_merge($createdThreads, Thread::where('protocol_id', $protocol->getKey())->get()->all());
        }

        // Create comments for threads and votes for both threads and comments
        $commentBodies = [
            'I\'ve been following this for 3 weeks and feel amazing!',
            'Has anyone experienced any side effects?',
            'This protocol really helped with my energy levels.',
            'I\'m thinking of starting this next week.',
            'Great tips, thanks for sharing!',
            'How long did it take you to see results?',
            'I had some issues at first but they resolved.',
            'This is exactly what I needed!',
            'Can you share your daily routine?',
            'I\'m skeptical but willing to try.',
        ];

        $allComments = [];
        foreach ($createdThreads as $thread) {
            // Create 3-6 comments per thread
            $commentCount = rand(3, 6);
            $threadComments = [];

            $commentData = [];
            for ($i = 0; $i < $commentCount; $i++) {
                $commentData[] = [
                    'thread_id' => $thread->getKey(),
                    'parent_id' => null,
                    'body' => $commentBodies[array_rand($commentBodies)],
                    'author' => $users->random()->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($commentData)) {
                Comment::insert($commentData);
                $threadComments = array_merge($threadComments, Comment::where('thread_id', $thread->getKey())->get()->all());
                $allComments = array_merge($allComments, $threadComments);
            }

            // Create 1-3 replies for some comments
            foreach ($threadComments as $comment) {
                if (rand(0, 1)) {
                    $replyCount = rand(1, 3);
                    $replyData = [];
                    $replyBodies = [
                        'Thanks for sharing your experience!',
                        'I agree with your point.',
                        'That is very helpful, thank you.',
                        'I had a similar experience.',
                        'Can you elaborate more on that?',
                        'This is encouraging to hear.',
                        'I will try this approach as well.',
                        'How long did it take for you to notice changes?',
                        'I appreciate your feedback.',
                        'Great advice!',
                    ];
                    for ($j = 0; $j < $replyCount; $j++) {
                        $replyData[] = [
                            'thread_id' => $thread->getKey(),
                            'parent_id' => $comment->getKey(),
                            'body' => $replyBodies[array_rand($replyBodies)],
                            'author' => $users->random()->name,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($replyData)) {
                        Comment::insert($replyData);
                        $allComments = array_merge($allComments, Comment::where('thread_id', $thread->getKey())->get()->all());
                    }
                }
            }

            // Vote on threads - ensure unique votes per user
            $threadVoteData = [];
            $threadVoters = $users->random(min(3, $users->count()));
            foreach ($threadVoters as $user) {
                $threadVoteData[] = [
                    'user_id' => $user->id,
                    'votable_type' => Thread::class,
                    'votable_id' => $thread->getKey(),
                    'type' => rand(0, 1) ? 'upvote' : 'downvote',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($threadVoteData)) {
                Vote::insert($threadVoteData);
            }

            // Vote on comments - ensure unique votes per user
            $commentVoteData = [];
            foreach ($threadComments as $comment) {
                $commentVoters = $users->random(min(2, $users->count()));
                foreach ($commentVoters as $user) {
                    $commentVoteData[] = [
                        'user_id' => $user->id,
                        'votable_type' => Comment::class,
                        'votable_id' => $comment->getKey(),
                        'type' => rand(0, 1) ? 'upvote' : 'downvote',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (!empty($commentVoteData)) {
                Vote::insert($commentVoteData);
            }
        }

        // Create reviews for protocols
        $reviewFeedbacks = [
            'Great protocol with clear instructions. Works well in practice.',
            'I saw improvements after a month. Highly recommended.',
            'No feedback provided.',
            'Easy to follow and effective.',
            'I had some issues at first but customer support was helpful.',
            'The protocol is good, but it takes patience to see results.',
            'I would suggest adding more details for beginners.',
            'Helped me achieve my goals. Thank you!',
            'Not sure if it works for everyone, but it worked for me.',
            'Affordable and practical.',
        ];
        DB::beginTransaction();
        try {
            foreach ($createdProtocols as $protocol) {
                // Create 2-5 reviews per protocol
                $reviewCount = rand(2, 5);
                $reviewData = [];
                for ($i = 0; $i < $reviewCount; $i++) {
                    $reviewData[] = [
                        'protocol_id' => $protocol->getKey(),
                        'rating' => rand(3, 5),
                        'feedback' => $reviewFeedbacks[array_rand($reviewFeedbacks)],
                        'author' => $users->random()->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($reviewData)) {
                    Review::insert($reviewData);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . User::count() . ' users');
        $this->command->info('- ' . Protocol::count() . ' protocols');
        $this->command->info('- ' . Thread::count() . ' threads');
        $this->command->info('- ' . Comment::count() . ' comments');
        $this->command->info('- ' . Review::count() . ' reviews');
        $this->command->info('- ' . Vote::count() . ' votes');
        $this->command->info('Database seeded successfully!');
    }
}
