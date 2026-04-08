<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Services\AI\EmbeddingService;
use App\Services\Vector\PineconeService;

class KnowledgeBaseSeeder extends Seeder
{
    public function __construct(
        private EmbeddingService $embedder,
        private PineconeService $pinecone,
    ) {}

    public function run(): void
    {
        DB::table('knowledge_base')->truncate();

        $knowledge = $this->getKnowledge();

        foreach ($knowledge as $entry) {
            $coach = DB::table('coaches')->where('name', $entry['coach'])->first();
            if (!$coach) continue;

            $vectorId = 'kb-' . $coach->id . '-' . uniqid();
            $vector   = $this->embedder->embed($entry['title'] . ' ' . $entry['content']);

            $this->pinecone->upsert(
                namespace: $coach->pinecone_namespace,
                id: $vectorId,
                vector: $vector,
                metadata: [
                    'title'    => $entry['title'],
                    'message'  => substr($entry['content'], 0, 500),
                    'coach_id' => $coach->id,
                ]
            );

            DB::table('knowledge_base')->insert([
                'coach_id'           => $coach->id,
                'title'              => $entry['title'],
                'content'            => $entry['content'],
                'pinecone_vector_id' => $vectorId,
                'pinecone_namespace' => $coach->pinecone_namespace,
                'file_type'          => 'text',
                'is_synced'          => 1,
                'is_active'          => 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $this->command->info("Synced: [{$coach->name}] {$entry['title']}");
        }

        $this->command->info('Knowledge base seeded and synced to Pinecone successfully.');
    }

    private function getKnowledge(): array
    {
        return [

            // ── DIABETES COACH ────────────────────────────────────────────────
            [
                'coach' => 'Diabetes Coach',
                'title' => 'Carbohydrate Management for Diabetes',
                'content' => 'For diabetics, carbohydrate quality and timing matters more than just quantity. Prefer complex carbs like jowar, bajra, ragi, brown rice, and whole dals over refined flour (maida) and white rice. Spread carbs evenly across 3 meals. Avoid skipping meals as it causes blood sugar fluctuations. Pair carbs with protein and fat to slow glucose absorption. Ideal plate: 50% vegetables, 25% protein, 25% complex carbs.',
            ],
            [
                'coach' => 'Diabetes Coach',
                'title' => 'Indian Foods Good for Blood Sugar Control',
                'content' => 'Best Indian foods for diabetes: bitter gourd (karela), fenugreek seeds (methi), amla, jamun, turmeric, cinnamon, flaxseeds, chia seeds, leafy greens (palak, methi), cucumber, bottle gourd (lauki). Methi water in the morning helps insulin sensitivity. Karela juice 2-3 times a week helps lower blood sugar naturally. Avoid: white rice in large portions, maida, sugary chai, fruit juices, packaged snacks.',
            ],
            [
                'coach' => 'Diabetes Coach',
                'title' => 'Exercise and Blood Sugar for Diabetics',
                'content' => 'Exercise is one of the most powerful tools for blood sugar management. A 30-minute walk after meals significantly reduces post-meal glucose spikes. Yoga poses like Mandukasana, Paschimottanasana, and Dhanurasana help stimulate the pancreas. Strength training 2-3 times a week improves insulin sensitivity. Always carry a small snack during exercise to prevent hypoglycemia. Check blood sugar before and after exercise initially.',
            ],

            // ── DIET & NUTRITION COACH ────────────────────────────────────────
            [
                'coach' => 'Diet & Nutrition Coach',
                'title' => 'Balanced Indian Diet Principles',
                'content' => 'A balanced Indian diet includes dal (protein), sabzi (vitamins/minerals), roti or rice (carbs), dahi (probiotics), and ghee (healthy fat). Eat 3 main meals and 1-2 small snacks. Do not skip breakfast — it sets metabolism for the day. Include seasonal fruits and vegetables. Use whole spices like turmeric, cumin, coriander which have anti-inflammatory properties. Avoid processed foods, packaged snacks, and excessive sugar.',
            ],
            [
                'coach' => 'Diet & Nutrition Coach',
                'title' => 'Protein Sources for Vegetarians in India',
                'content' => 'Best vegetarian protein sources: moong dal, masoor dal, chana dal, rajma, chole, paneer, tofu, soya chunks, Greek yogurt, eggs (for eggetarians), quinoa, amaranth (rajgira). Combine dal with rice or roti for complete amino acid profile. Sprouts are excellent — soak overnight and eat raw or lightly cooked. Aim for 0.8-1g protein per kg body weight daily. Protein at every meal helps with satiety and muscle maintenance.',
            ],
            [
                'coach' => 'Diet & Nutrition Coach',
                'title' => 'Healthy Indian Snack Options',
                'content' => 'Healthy Indian snacks: roasted chana, makhana (fox nuts), handful of mixed nuts, fruit with peanut butter, sprout chaat, cucumber with hummus, buttermilk (chaas), coconut water, boiled eggs, paneer cubes with spices, roasted seeds mix. Avoid: namkeen, biscuits, chips, fried snacks, packaged juices. Snack timing: mid-morning (10-11am) and evening (4-5pm) works best for most people.',
            ],

            // ── FITNESS COACH ─────────────────────────────────────────────────
            [
                'coach' => 'Fitness Coach',
                'title' => 'Beginner Home Workout Routine',
                'content' => 'For beginners, start with 20-30 minutes 3 times a week. Warm up 5 minutes with light walking or jumping jacks. Basic exercises: squats (3x10), push-ups or wall push-ups (3x8), lunges (3x10 each leg), plank (3x20 seconds), glute bridges (3x12). Cool down with stretching. Progress by adding reps or sets each week. Rest days are as important as workout days — muscles grow during rest.',
            ],
            [
                'coach' => 'Fitness Coach',
                'title' => 'Yoga for Daily Fitness',
                'content' => 'Daily yoga routine for overall fitness: Surya Namaskar (5-10 rounds) for full body workout, Warrior poses for strength, Downward dog for flexibility, Child pose for recovery, Pranayama (Anulom Vilom, Kapalbhati) for lung capacity and stress. Morning yoga on empty stomach is ideal. Even 20 minutes daily makes a significant difference in flexibility, strength, and mental clarity. Yoga also helps with hormonal balance and digestion.',
            ],
            [
                'coach' => 'Fitness Coach',
                'title' => 'Nutrition Around Workouts',
                'content' => 'Pre-workout (30-60 min before): banana with peanut butter, dates, or a small bowl of oats. Post-workout (within 30-45 min): protein-rich meal — paneer, eggs, dal, or protein shake with milk. Stay hydrated — drink water before, during, and after exercise. Avoid heavy meals right before workout. For weight loss, morning fasted cardio (light walk) can be effective. For muscle building, ensure adequate protein throughout the day.',
            ],

            // ── PCOS & THYROID COACH ──────────────────────────────────────────
            [
                'coach' => 'PCOS & Thyroid Coach',
                'title' => 'Diet for PCOS Management',
                'content' => 'PCOS diet focuses on reducing insulin resistance. Avoid refined carbs, sugar, and processed foods. Eat low glycemic index foods: vegetables, legumes, whole grains, berries. Include anti-inflammatory foods: turmeric, ginger, omega-3 rich foods (flaxseeds, walnuts, fatty fish). Spearmint tea helps reduce androgens. Inositol (found in citrus fruits) improves insulin sensitivity. Eat every 3-4 hours to maintain stable blood sugar. Avoid skipping meals.',
            ],
            [
                'coach' => 'PCOS & Thyroid Coach',
                'title' => 'Thyroid Health and Diet',
                'content' => 'For hypothyroidism: avoid raw cruciferous vegetables (cabbage, cauliflower, broccoli) in large amounts as they can interfere with thyroid function — cooking reduces this effect. Include selenium-rich foods: Brazil nuts, sunflower seeds, eggs. Iodine from iodized salt is important. Avoid soy in excess. Take thyroid medication on empty stomach, wait 30-60 minutes before eating. For hyperthyroidism: avoid excess iodine, caffeine, and stimulants.',
            ],
            [
                'coach' => 'PCOS & Thyroid Coach',
                'title' => 'Exercise for Hormonal Balance',
                'content' => 'For PCOS and thyroid: avoid excessive high-intensity exercise which can spike cortisol and worsen hormonal imbalance. Best exercises: brisk walking 30-45 minutes daily, yoga (especially restorative and yin yoga), swimming, light strength training 2-3 times a week. Avoid overtraining. Stress management is crucial — high cortisol worsens both PCOS and thyroid conditions. Prioritize sleep and recovery.',
            ],

            // ── MENTAL WELLNESS COACH ─────────────────────────────────────────
            [
                'coach' => 'Mental Wellness Coach',
                'title' => 'Managing Anxiety Naturally',
                'content' => 'Natural anxiety management: deep breathing (4-7-8 technique — inhale 4 counts, hold 7, exhale 8), progressive muscle relaxation, grounding technique (5-4-3-2-1: name 5 things you see, 4 you hear, 3 you can touch, 2 you smell, 1 you taste). Reduce caffeine and sugar. Regular exercise releases endorphins. Journaling helps process anxious thoughts. Limit news and social media. Ashwagandha and brahmi are Ayurvedic herbs that help with anxiety.',
            ],
            [
                'coach' => 'Mental Wellness Coach',
                'title' => 'Building Emotional Resilience',
                'content' => 'Emotional resilience is built through: consistent sleep schedule, regular physical activity, strong social connections, mindfulness practice (even 5 minutes daily), gratitude journaling (3 things daily), setting healthy boundaries, and self-compassion. When feeling overwhelmed, use STOP technique: Stop, Take a breath, Observe your feelings without judgment, Proceed with intention. Talk to someone you trust — sharing reduces emotional burden significantly.',
            ],
            [
                'coach' => 'Mental Wellness Coach',
                'title' => 'Stress and Gut Connection',
                'content' => 'The gut-brain axis means stress directly affects digestion and vice versa. Chronic stress causes IBS, bloating, acidity, and poor nutrient absorption. Probiotic foods (dahi, kanji, idli, dosa) support gut health and improve mood. Magnesium-rich foods (dark chocolate, nuts, seeds, leafy greens) help calm the nervous system. Avoid stress eating — eat mindfully, chew slowly. A healthy gut produces 90% of serotonin (the happiness hormone).',
            ],

            // ── SLEEP COACH ───────────────────────────────────────────────────
            [
                'coach' => 'Sleep Coach',
                'title' => 'Sleep Hygiene Fundamentals',
                'content' => 'Good sleep hygiene: fixed sleep and wake time (even weekends), dark and cool room (18-20°C ideal), no screens 1 hour before bed (blue light suppresses melatonin), avoid caffeine after 2pm, avoid heavy meals within 2 hours of sleep. Wind-down routine: warm shower, light reading, gentle stretching, or meditation. Keep phone outside bedroom if possible. Consistent sleep schedule is more important than total hours.',
            ],
            [
                'coach' => 'Sleep Coach',
                'title' => 'Natural Remedies for Better Sleep',
                'content' => 'Natural sleep aids: warm turmeric milk (haldi doodh) 30 minutes before bed, ashwagandha helps reduce cortisol and improve sleep quality, magnesium glycinate supplement, chamomile tea, tart cherry juice (natural melatonin source). Foot massage with warm sesame or coconut oil before bed is an Ayurvedic practice that promotes deep sleep. Avoid alcohol — it disrupts sleep architecture even if it helps you fall asleep initially.',
            ],

            // ── WEIGHT LOSS COACH ─────────────────────────────────────────────
            [
                'coach' => 'Weight Loss Coach',
                'title' => 'Sustainable Weight Loss Principles',
                'content' => 'Sustainable weight loss is 0.5-1 kg per week. Create a moderate calorie deficit (300-500 calories/day) — not too aggressive. Focus on food quality, not just quantity. High protein diet (1.2-1.6g per kg body weight) preserves muscle during weight loss. Fiber-rich foods (vegetables, legumes, whole grains) keep you full longer. Drink water before meals. Never skip breakfast. Avoid liquid calories — chai with sugar, juices, cold drinks add up quickly.',
            ],
            [
                'coach' => 'Weight Loss Coach',
                'title' => 'Indian Weight Loss Meal Plan Principles',
                'content' => 'Breakfast: poha with vegetables, oats upma, moong dal chilla, or eggs. Lunch: dal + sabzi + 1-2 roti + salad (biggest meal of the day). Dinner: light — soup, salad, or small portion of dal-rice. Snacks: fruits, roasted chana, makhana, buttermilk. Avoid: maida products, fried foods, packaged snacks, sugary drinks. Intermittent fasting (16:8) works well for many Indians — skip breakfast or have it late, eat between 12pm-8pm.',
            ],

            // ── PREGNANCY COACH ───────────────────────────────────────────────
            [
                'coach' => 'Pregnancy Coach',
                'title' => 'Nutrition During Pregnancy',
                'content' => 'Key nutrients during pregnancy: folic acid (leafy greens, fortified foods — critical in first trimester for neural tube development), iron (dal, spinach, jaggery, with vitamin C for absorption), calcium (dairy, ragi, sesame seeds), omega-3 (walnuts, flaxseeds, fatty fish), vitamin D (sunlight, eggs, fortified milk). Eat small frequent meals to manage nausea. Stay hydrated. Avoid: raw papaya, pineapple in excess, unpasteurized dairy, raw sprouts, excess caffeine.',
            ],
            [
                'coach' => 'Pregnancy Coach',
                'title' => 'Safe Exercise During Pregnancy',
                'content' => 'Safe exercises during pregnancy: walking (30 minutes daily), prenatal yoga, swimming, light stretching. Avoid: heavy lifting, high-impact exercises, lying flat on back after first trimester, contact sports. Kegel exercises strengthen pelvic floor and help with delivery and recovery. Listen to your body — if something feels uncomfortable, stop. Stay cool and hydrated during exercise. Always consult your gynecologist before starting any exercise routine during pregnancy.',
            ],

            // ── POSTPARTUM COACH ──────────────────────────────────────────────
            [
                'coach' => 'Postpartum Coach',
                'title' => 'Postpartum Nutrition for Recovery',
                'content' => 'Postpartum nutrition focuses on recovery and breastfeeding support. Traditional Indian foods are excellent: panjiri (whole wheat, ghee, dry fruits), methi ladoo (increases milk supply), ajwain water (aids digestion), warm dal and khichdi (easy to digest), gondh ke ladoo (strengthens bones and joints). Iron-rich foods for blood replenishment. Increase calorie intake by 300-500 calories if breastfeeding. Stay well hydrated — breastfeeding requires extra fluids.',
            ],
            [
                'coach' => 'Postpartum Coach',
                'title' => 'Postpartum Mental Health',
                'content' => 'Baby blues (mood swings, crying, anxiety) in first 2 weeks are normal due to hormonal changes. Postpartum depression (PPD) is more serious — persistent sadness, inability to bond with baby, feeling hopeless — requires professional support. Signs to watch: not sleeping even when baby sleeps, feeling detached, intrusive thoughts. Self-care is not selfish — ask for help, accept support, sleep when baby sleeps. Omega-3, vitamin D, and magnesium support postpartum mood.',
            ],

            // ── ENERGY COACH ──────────────────────────────────────────────────
            [
                'coach' => 'Energy Coach',
                'title' => 'Beating Fatigue Naturally',
                'content' => 'Common causes of fatigue: iron deficiency anemia (very common in Indian women), vitamin B12 deficiency (especially vegetarians), vitamin D deficiency, thyroid issues, poor sleep, dehydration, and blood sugar fluctuations. Natural energy boosters: iron-rich foods with vitamin C, B12 from dairy/eggs/fortified foods, sunlight for vitamin D, ashwagandha, regular meal timing, adequate hydration (2-3 liters water daily), and 7-8 hours sleep.',
            ],
            [
                'coach' => 'Energy Coach',
                'title' => 'Energy-Boosting Indian Foods',
                'content' => 'Best energy foods: banana (quick energy + potassium), dates (iron + natural sugar), almonds and walnuts (sustained energy), coconut water (electrolytes), sweet potato (complex carbs + vitamin A), rajma and chole (iron + protein), amla (vitamin C + antioxidants), tulsi and ashwagandha tea (adaptogenic herbs that fight fatigue). Avoid energy drains: excess sugar (causes energy crash), skipping meals, excess caffeine, processed foods.',
            ],

            // ── STRESS COACH ──────────────────────────────────────────────────
            [
                'coach' => 'Stress Coach',
                'title' => 'Practical Stress Relief Techniques',
                'content' => 'Immediate stress relief: box breathing (inhale 4, hold 4, exhale 4, hold 4), cold water on face and wrists, 5-minute walk, progressive muscle relaxation. Daily stress management: 10-minute morning meditation, journaling, limiting news to 30 minutes, saying no to non-essential commitments, spending time in nature, connecting with friends. Ayurvedic stress relief: Brahmi, Ashwagandha, Shankhpushpi herbs, Abhyanga (self-massage with warm oil).',
            ],
            [
                'coach' => 'Stress Coach',
                'title' => 'Stress and Weight Connection',
                'content' => 'Chronic stress raises cortisol which increases belly fat storage, triggers sugar cravings, disrupts sleep, and slows metabolism. Stress eating is a real physiological response — not a lack of willpower. To break the cycle: identify stress triggers, find non-food coping strategies (walk, call a friend, breathe), keep healthy snacks accessible, eat regular meals to prevent blood sugar drops that worsen stress response. Magnesium and B vitamins are depleted by stress.',
            ],

            // ── HABIT COACH ───────────────────────────────────────────────────
            [
                'coach' => 'Habit Coach',
                'title' => 'Building Healthy Habits That Stick',
                'content' => 'Habits form through: cue → routine → reward loop. To build a new habit: attach it to an existing habit (habit stacking), start incredibly small (2-minute rule), track it visually (habit tracker), celebrate small wins immediately. It takes 21-66 days to form a habit depending on complexity. Focus on one habit at a time. Identity-based habits work best — "I am someone who exercises" vs "I want to exercise". Environment design: make healthy choices easy and unhealthy choices harder.',
            ],
            [
                'coach' => 'Habit Coach',
                'title' => 'Morning Routine for Health',
                'content' => 'Powerful morning routine: wake at consistent time, drink 1-2 glasses warm water (with lemon optionally), 5-10 minutes sunlight exposure (vitamin D + circadian rhythm), 10-20 minutes movement (yoga, walk, or stretching), nutritious breakfast within 1-2 hours of waking. Avoid: checking phone first thing (raises cortisol), skipping breakfast, rushing. Even a 20-minute morning routine creates momentum for the entire day. Consistency matters more than perfection.',
            ],

            // ── VISION COACH ──────────────────────────────────────────────────
            [
                'coach' => 'Vision Coach',
                'title' => 'Eye Health and Screen Time',
                'content' => 'With increasing screen time, eye strain is very common. Follow 20-20-20 rule: every 20 minutes, look at something 20 feet away for 20 seconds. Blink consciously — screen use reduces blink rate causing dry eyes. Adjust screen brightness to match surroundings. Keep screen at arm\'s length and slightly below eye level. Use night mode after sunset. Take a 5-minute screen break every hour. Palming (covering eyes with warm palms) relieves eye strain instantly.',
            ],
            [
                'coach' => 'Vision Coach',
                'title' => 'Nutrition for Eye Health',
                'content' => 'Key nutrients for eye health: Vitamin A (carrots, sweet potato, leafy greens — prevents night blindness), Lutein and Zeaxanthin (spinach, kale, eggs — protect against macular degeneration), Omega-3 (flaxseeds, walnuts, fatty fish — reduces dry eyes), Vitamin C (amla, citrus fruits — prevents cataracts), Zinc (pumpkin seeds, legumes — supports retinal health). Triphala eye wash is an Ayurvedic remedy for eye health. Stay hydrated for tear production.',
            ],
        ];
    }
}
