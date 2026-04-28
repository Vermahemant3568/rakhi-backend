<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; background: #f7f4ff; color: #1a1a1a; font-size: 13px; line-height: 1.75; }

  .header { background-color: #6d28d9; color: #ffffff; padding: 32px 32px 28px; }
  .header-top { font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; opacity: 0.75; margin-bottom: 10px; }
  .header-greeting { font-size: 24px; font-weight: bold; margin-bottom: 4px; }
  .header-sub { font-size: 13px; opacity: 0.85; margin-bottom: 18px; }
  .header-meta-table { width: 100%; border-collapse: collapse; }
  .header-meta-table td { font-size: 11px; color: rgba(255,255,255,0.75); padding: 0 16px 0 0; white-space: nowrap; }
  .header-meta-table td strong { display: block; font-size: 13px; color: #ffffff; font-weight: bold; }

  .page { padding: 26px 28px 40px; }
  .section { margin-bottom: 28px; }
  .section-title { font-size: 15px; font-weight: bold; color: #3b0764; margin-bottom: 14px; }

  .badge { display: inline-block; background: #ede9fe; color: #5b21b6; padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; margin: 3px 5px 3px 0; border: 1px solid #ddd6fe; }

  .nutrition-table { width: 100%; border-collapse: separate; border-spacing: 6px; }
  .nutrient-card { background: #ffffff; border-radius: 12px; padding: 14px 8px 12px; text-align: center; border: 1px solid #ede9fe; }
  .nutrient-card .n-value { font-size: 18px; font-weight: bold; color: #6d28d9; line-height: 1.2; }
  .nutrient-card .n-label { font-size: 10px; color: #888; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

  .meal-card { background: #ffffff; border-radius: 12px; border: 1px solid #ede9fe; margin-bottom: 12px; overflow: hidden; }
  .meal-card-header { background: #f5f0ff; padding: 10px 16px; border-bottom: 1px solid #ede9fe; }
  .meal-time-badge { display: inline-block; background: #6d28d9; color: #fff; font-size: 10px; font-weight: bold; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 4px; }
  .meal-timing { font-size: 11px; color: #7c3aed; margin-top: 2px; }
  .meal-name { font-size: 14px; font-weight: bold; color: #2d2d2d; margin-top: 2px; }
  .meal-card-body { padding: 12px 16px; }
  .meal-desc { font-size: 13px; color: #555; line-height: 1.75; }
  .meal-meta-row { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
  .meal-kcal { display: inline-block; background: #faf5ff; color: #6d28d9; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 20px; border: 1px solid #ddd6fe; }
  .meal-protein { display: inline-block; background: #f0fdf4; color: #065f46; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 20px; border: 1px solid #d1fae5; }
  .condition-note { margin-top: 8px; font-size: 11px; color: #7c3aed; font-style: italic; }
  .alternatives-label { font-size: 11px; font-weight: bold; color: #6b7280; margin-top: 8px; margin-bottom: 3px; }
  .alt-pill { display: inline-block; background: #f3f4f6; color: #374151; font-size: 11px; padding: 2px 9px; border-radius: 20px; margin: 2px 3px 0 0; border: 1px solid #e5e7eb; }

  .avoid-card { background: #fff7ed; border-radius: 10px; border: 1px solid #fed7aa; padding: 10px 14px; margin-bottom: 8px; }
  .avoid-food { font-size: 13px; font-weight: bold; color: #c2410c; }
  .avoid-reason { font-size: 12px; color: #78350f; margin-top: 2px; }

  .tip-card { background: #ffffff; border-radius: 12px; border: 1px solid #fde68a; padding: 14px 16px; margin-bottom: 10px; }
  .tip-label { font-size: 11px; font-weight: bold; color: #b45309; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 5px; }
  .tip-text { font-size: 13px; color: #4b4b4b; line-height: 1.75; }

  .precaution-card { background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe; padding: 10px 14px; margin-bottom: 8px; }
  .precaution-text { font-size: 12px; color: #1e40af; line-height: 1.6; }

  .divider { border: none; border-top: 1px solid #e9e3ff; margin: 22px 0; }

  .footer { margin-top: 36px; text-align: center; color: #aaa; font-size: 11px; border-top: 1px solid #e9e3ff; padding-top: 16px; line-height: 1.9; }
  .footer-brand { font-size: 12px; font-weight: bold; color: #6d28d9; margin-top: 6px; }
</style>
</head>
<body>

  <div class="header">
    <div class="header-top">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; Diet Plan</div>
    <div class="header-greeting">Hi {{ $user->first_name }}! &#128075;</div>
    <div class="header-sub">Here's your personalized diet plan, crafted just for you.</div>
    <table class="header-meta-table">
      <tr>
        <td><strong>{{ $user->getAge() }} yrs</strong>Age</td>
        <td><strong>{{ $user->weight }} kg</strong>Weight</td>
        <td><strong>{{ $user->height }} cm</strong>Height</td>
        <td><strong>{{ ucfirst($user->diet_preference ?? 'Not specified') }}</strong>Diet Type</td>
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- Goals -->
    <div class="section">
      <div class="section-title">&#127919; Your Health Goals</div>
      @foreach($user->goals as $goal)
        <span class="badge">{{ $goal->name }}</span>
      @endforeach
    </div>

    <hr class="divider">

    <!-- Daily Targets -->
    @if(isset($plan['daily_targets']))
    <div class="section">
      <div class="section-title">&#9889; Daily Nutrition Targets</div>
      <table class="nutrition-table">
        <tr>
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['calories'] ?? '—' }}</div>
            <div class="n-label">Calories</div>
          </td>
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['protein'] ?? '—' }}g</div>
            <div class="n-label">Protein</div>
          </td>
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['carbs'] ?? '—' }}g</div>
            <div class="n-label">Carbs</div>
          </td>
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['fat'] ?? '—' }}g</div>
            <div class="n-label">Fat</div>
          </td>
          @if(!empty($plan['daily_targets']['water_litres']))
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['water_litres'] }}L</div>
            <div class="n-label">Water</div>
          </td>
          @endif
          @if(!empty($plan['daily_targets']['fibre_g']))
          <td class="nutrient-card">
            <div class="n-value">{{ $plan['daily_targets']['fibre_g'] }}g</div>
            <div class="n-label">Fibre</div>
          </td>
          @endif
        </tr>
      </table>
    </div>
    <hr class="divider">
    @endif

    <!-- Meal Plan -->
    @if(isset($plan['meals']))
    <div class="section">
      <div class="section-title">&#127859; Your Daily Meal Plan</div>
      @foreach($plan['meals'] as $meal)
      <div class="meal-card">
        <div class="meal-card-header">
          <div class="meal-time-badge">{{ ucfirst($meal['time'] ?? 'Meal') }}</div>
          @if(!empty($meal['timing_note']))
            <div class="meal-timing">&#128336; {{ $meal['timing_note'] }}</div>
          @endif
          @if(!empty($meal['name']))
            <div class="meal-name">{{ $meal['name'] }}</div>
          @endif
        </div>
        <div class="meal-card-body">
          @if(!empty($meal['description']))
            <div class="meal-desc">{{ $meal['description'] }}</div>
          @endif
          <div class="meal-meta-row">
            @if(isset($meal['calories']) && $meal['calories'] > 0)
              <span class="meal-kcal">&#128293; ~{{ $meal['calories'] }} kcal</span>
            @endif
            @if(!empty($meal['protein_g']) && $meal['protein_g'] > 0)
              <span class="meal-protein">&#128167; {{ $meal['protein_g'] }}g protein</span>
            @endif
          </div>
          @if(!empty($meal['condition_note']))
            <div class="condition-note">&#10024; {{ $meal['condition_note'] }}</div>
          @endif
          @if(!empty($meal['alternatives']))
            <div class="alternatives-label">Alternatives:</div>
            @foreach($meal['alternatives'] as $alt)
              <span class="alt-pill">{{ $alt }}</span>
            @endforeach
          @endif
        </div>
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Foods to Avoid -->
    @if(!empty($plan['foods_to_avoid']))
    <div class="section">
      <div class="section-title">&#128683; Foods to Avoid</div>
      @foreach($plan['foods_to_avoid'] as $item)
      <div class="avoid-card">
        <div class="avoid-food">{{ $item['food'] ?? '' }}</div>
        @if(!empty($item['reason']))
          <div class="avoid-reason">{{ $item['reason'] }}</div>
        @endif
      </div>
      @endforeach
    </div>
    <hr class="divider">
    @endif

    <!-- Tips -->
    @if(isset($plan['tips']))
    <div class="section">
      <div class="section-title">&#128172; Coaching Tips</div>
      @foreach($plan['tips'] as $tip)
      <div class="tip-card">
        <div class="tip-label">&#128161; Rakhi says:</div>
        <div class="tip-text">{{ $tip }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <!-- Precautions -->
    @if(!empty($plan['precautions']))
    <div class="section">
      <div class="section-title">&#9888; Precautions</div>
      @foreach($plan['precautions'] as $p)
      <div class="precaution-card">
        <div class="precaution-text">&#8226; {{ $p }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <div class="footer">
      <p>
        This plan is prepared by Rakhi Health Coach for personal wellness guidance only.<br>
        It is not a substitute for professional medical advice.
      </p>
      <div class="footer-brand">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
