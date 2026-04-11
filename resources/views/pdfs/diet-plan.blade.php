<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', sans-serif;
    background: #f7f4ff;
    color: #1a1a1a;
    font-size: 13px;
    line-height: 1.75;
  }

  /* ── Header ── */
  .header {
    background-color: #6d28d9;
    color: #ffffff;
    padding: 32px 32px 28px;
  }
  .header-top {
    font-size: 11px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    opacity: 0.75;
    margin-bottom: 10px;
  }
  .header-greeting {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 4px;
    letter-spacing: 0.2px;
  }
  .header-sub {
    font-size: 13px;
    opacity: 0.85;
    margin-bottom: 18px;
  }
  .header-meta-table { width: 100%; border-collapse: collapse; }
  .header-meta-table td {
    font-size: 11px;
    color: rgba(255,255,255,0.75);
    padding: 0 16px 0 0;
    white-space: nowrap;
  }
  .header-meta-table td strong {
    display: block;
    font-size: 13px;
    color: #ffffff;
    font-weight: bold;
  }

  /* ── Page wrapper ── */
  .page { padding: 26px 28px 40px; }

  /* ── Section ── */
  .section { margin-bottom: 28px; }

  .section-title {
    font-size: 15px;
    font-weight: bold;
    color: #3b0764;
    margin-bottom: 14px;
  }

  /* ── Goals badges ── */
  .badge {
    display: inline-block;
    background: #ede9fe;
    color: #5b21b6;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin: 3px 5px 3px 0;
    border: 1px solid #ddd6fe;
  }

  /* ── Nutrition cards ── */
  .nutrition-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 8px;
  }
  .nutrient-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 16px 10px 14px;
    text-align: center;
    width: 25%;
    border: 1px solid #ede9fe;
  }
  .nutrient-card .n-icon {
    font-size: 22px;
    margin-bottom: 6px;
  }
  .nutrient-card .n-value {
    font-size: 20px;
    font-weight: bold;
    color: #6d28d9;
    line-height: 1.2;
  }
  .nutrient-card .n-label {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* ── Meal cards ── */
  .meal-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #ede9fe;
    padding: 0;
    margin-bottom: 12px;
    overflow: hidden;
  }
  .meal-card-header {
    background: #f5f0ff;
    padding: 10px 16px;
    border-bottom: 1px solid #ede9fe;
  }
  .meal-time-badge {
    display: inline-block;
    background: #6d28d9;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 4px;
  }
  .meal-name {
    font-size: 14px;
    font-weight: bold;
    color: #2d2d2d;
  }
  .meal-card-body {
    padding: 12px 16px;
  }
  .meal-desc {
    font-size: 13px;
    color: #555;
    line-height: 1.75;
  }
  .meal-kcal-row {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px dashed #ede9fe;
  }
  .meal-kcal {
    display: inline-block;
    background: #faf5ff;
    color: #6d28d9;
    font-size: 12px;
    font-weight: bold;
    padding: 3px 12px;
    border-radius: 20px;
    border: 1px solid #ddd6fe;
  }

  /* ── Tip / Rakhi says ── */
  .tip-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #fde68a;
    padding: 14px 16px;
    margin-bottom: 10px;
  }
  .tip-label {
    font-size: 11px;
    font-weight: bold;
    color: #b45309;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 5px;
  }
  .tip-text {
    font-size: 13px;
    color: #4b4b4b;
    line-height: 1.75;
  }

  /* ── Divider ── */
  .divider {
    border: none;
    border-top: 1px solid #e9e3ff;
    margin: 24px 0;
  }

  /* ── Footer ── */
  .footer {
    margin-top: 36px;
    text-align: center;
    color: #aaa;
    font-size: 11px;
    border-top: 1px solid #e9e3ff;
    padding-top: 16px;
    line-height: 1.9;
  }
  .footer-brand {
    font-size: 12px;
    font-weight: bold;
    color: #6d28d9;
    margin-top: 6px;
  }
</style>
</head>
<body>

  <!-- ── Header ── -->
  <div class="header">
    <div class="header-top">&#127807; Rakhi AI &nbsp;&bull;&nbsp; Health Coach</div>
    <div class="header-greeting">Hi {{ $user->first_name }}! &#128075;</div>
    <div class="header-sub">Here's your personalized diet plan, crafted just for you.</div>
    <table class="header-meta-table">
      <tr>
        <td><strong>{{ $user->age() }} yrs</strong>Age</td>
        <td><strong>{{ $user->weight }} kg</strong>Weight</td>
        <td><strong>{{ $user->height }} cm</strong>Height</td>
        <td><strong>{{ ucfirst($user->diet_preference ?? 'Not specified') }}</strong>Diet Type</td>
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- ── Goals ── -->
    <div class="section">
      <div class="section-title">&#127919; Your Health Goals</div>
      @foreach($user->goals as $goal)
        <span class="badge">{{ $goal->name }}</span>
      @endforeach
    </div>

    <hr class="divider">

    <!-- ── Nutrition Targets ── -->
    @if(isset($plan['daily_targets']))
    <div class="section">
      <div class="section-title">&#9889; Daily Nutrition Targets</div>
      <table class="nutrition-table">
        <tr>
          <td class="nutrient-card">
            <div class="n-icon">&#128293;</div>
            <div class="n-value">{{ $plan['daily_targets']['calories'] ?? '—' }}</div>
            <div class="n-label">Calories</div>
          </td>
          <td class="nutrient-card">
            <div class="n-icon">&#128167;</div>
            <div class="n-value">{{ $plan['daily_targets']['protein'] ?? '—' }}g</div>
            <div class="n-label">Protein</div>
          </td>
          <td class="nutrient-card">
            <div class="n-icon">&#127807;</div>
            <div class="n-value">{{ $plan['daily_targets']['carbs'] ?? '—' }}g</div>
            <div class="n-label">Carbs</div>
          </td>
          <td class="nutrient-card">
            <div class="n-icon">&#129370;</div>
            <div class="n-value">{{ $plan['daily_targets']['fat'] ?? '—' }}g</div>
            <div class="n-label">Fat</div>
          </td>
        </tr>
      </table>
    </div>

    <hr class="divider">
    @endif

    <!-- ── Meal Plan ── -->
    @if(isset($plan['meals']))
    <div class="section">
      <div class="section-title">&#127859; Your Daily Meal Plan</div>
      @foreach($plan['meals'] as $meal)
      <div class="meal-card">
        <div class="meal-card-header">
          <div class="meal-time-badge">{{ ucfirst($meal['time'] ?? 'Meal') }}</div>
          @if(!empty($meal['name']))
            <div class="meal-name">{{ $meal['name'] }}</div>
          @endif
        </div>
        <div class="meal-card-body">
          @if(!empty($meal['description']))
            <div class="meal-desc">{{ $meal['description'] }}</div>
          @endif
          @if(isset($meal['calories']))
          <div class="meal-kcal-row">
            <span class="meal-kcal">&#128293; ~{{ $meal['calories'] }} kcal</span>
          </div>
          @endif
        </div>
      </div>
      @endforeach
    </div>

    <hr class="divider">
    @endif

    <!-- ── Tips ── -->
    @if(isset($plan['tips']))
    <div class="section">
      <div class="section-title">&#128172; Rakhi's Coaching Tips</div>
      @foreach($plan['tips'] as $tip)
      <div class="tip-card">
        <div class="tip-label">&#128161; Rakhi says:</div>
        <div class="tip-text">{{ $tip }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <!-- ── Footer ── -->
    <div class="footer">
      <p>
        This plan is prepared by Rakhi AI Health Coach for personal wellness guidance only.<br>
        It is not a substitute for professional medical advice.
      </p>
      <div class="footer-brand">&#127807; Rakhi AI &nbsp;&bull;&nbsp; rakhi.ai &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
