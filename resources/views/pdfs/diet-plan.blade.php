<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'DejaVu Sans', sans-serif;
    background: #fff;
    color: #1a1a1a;
    padding: 40px;
  }
  .header {
    background: linear-gradient(135deg, #7C3AED, #EC4899);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
  }
  .header h1 { font-size: 28px; margin-bottom: 6px; }
  .header p  { font-size: 14px; opacity: 0.85; }
  .user-info {
    background: #f8f4ff;
    border-left: 4px solid #7C3AED;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: flex;
    gap: 30px;
  }
  .user-info span { font-size: 13px; color: #555; }
  .user-info strong { color: #1a1a1a; }
  .section {
    margin-bottom: 28px;
  }
  .section h2 {
    font-size: 16px;
    color: #7C3AED;
    border-bottom: 2px solid #f0e6ff;
    padding-bottom: 8px;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .meal-card {
    background: #fafafa;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
  }
  .meal-card h3 {
    font-size: 14px;
    color: #333;
    margin-bottom: 8px;
  }
  .meal-card p {
    font-size: 13px;
    color: #666;
    line-height: 1.6;
  }
  .nutrition-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 20px;
  }
  .nutrient-box {
    background: #f8f4ff;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
  }
  .nutrient-box .value {
    font-size: 18px;
    font-weight: bold;
    color: #7C3AED;
  }
  .nutrient-box .label {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
  }
  .tip-box {
    background: #fff8e1;
    border-left: 4px solid #F59E0B;
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 10px;
  }
  .tip-box p { font-size: 13px; color: #555; line-height: 1.6; }
  .footer {
    margin-top: 40px;
    text-align: center;
    color: #aaa;
    font-size: 11px;
    border-top: 1px solid #eee;
    padding-top: 16px;
  }
  .badge {
    display: inline-block;
    background: #f0e6ff;
    color: #7C3AED;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    margin: 3px;
  }
</style>
</head>
<body>

  <!-- Header -->
  <div class="header">
    <h1>🌸 Personalized Diet Plan</h1>
    <p>Prepared by Rakhi AI Health Coach &bull; {{ $date }}</p>
  </div>

  <!-- User Info -->
  <div class="user-info">
    <span><strong>Name:</strong> {{ $user->first_name }} {{ $user->last_name }}</span>
    <span><strong>Age:</strong> {{ $user->age() }} years</span>
    <span><strong>Weight:</strong> {{ $user->weight }} kg</span>
    <span><strong>Height:</strong> {{ $user->height }} cm</span>
    <span><strong>Diet:</strong> {{ ucfirst($user->diet_preference ?? 'Not specified') }}</span>
  </div>

  <!-- Goals -->
  <div class="section">
    <h2>Your Health Goals</h2>
    @foreach($user->goals as $goal)
      <span class="badge">{{ $goal->name }}</span>
    @endforeach
  </div>

  <!-- Daily Nutrition Target -->
  @if(isset($plan['daily_targets']))
  <div class="section">
    <h2>Daily Nutrition Targets</h2>
    <div class="nutrition-grid">
      <div class="nutrient-box">
        <div class="value">{{ $plan['daily_targets']['calories'] ?? '—' }}</div>
        <div class="label">Calories</div>
      </div>
      <div class="nutrient-box">
        <div class="value">{{ $plan['daily_targets']['protein'] ?? '—' }}g</div>
        <div class="label">Protein</div>
      </div>
      <div class="nutrient-box">
        <div class="value">{{ $plan['daily_targets']['carbs'] ?? '—' }}g</div>
        <div class="label">Carbs</div>
      </div>
      <div class="nutrient-box">
        <div class="value">{{ $plan['daily_targets']['fat'] ?? '—' }}g</div>
        <div class="label">Fat</div>
      </div>
    </div>
  </div>
  @endif

  <!-- Meal Plan -->
  @if(isset($plan['meals']))
  <div class="section">
    <h2>Your Daily Meal Plan</h2>
    @foreach($plan['meals'] as $meal)
    <div class="meal-card">
      <h3>{{ ucfirst($meal['time'] ?? '') }} &mdash; {{ $meal['name'] ?? '' }}</h3>
      <p>{{ $meal['description'] ?? '' }}</p>
      @if(isset($meal['calories']))
        <p style="margin-top:6px; color:#7C3AED; font-size:12px;">
          ~{{ $meal['calories'] }} kcal
        </p>
      @endif
    </div>
    @endforeach
  </div>
  @endif

  <!-- Tips -->
  @if(isset($plan['tips']))
  <div class="section">
    <h2>Rakhi's Tips For You</h2>
    @foreach($plan['tips'] as $tip)
    <div class="tip-box">
      <p>💡 {{ $tip }}</p>
    </div>
    @endforeach
  </div>
  @endif

  <!-- Footer -->
  <div class="footer">
    <p>
      This plan is prepared by Rakhi AI Health Coach for personal
      wellness guidance only. It is not a substitute for
      professional medical advice.
    </p>
    <p style="margin-top:6px;">
      🌸 Rakhi AI &bull; rakhi.ai &bull; {{ $date }}
    </p>
  </div>

</body>
</html>
