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
    background: linear-gradient(135deg, #059669, #7C3AED);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
  }
  .header h1 { font-size: 28px; margin-bottom: 6px; }
  .header p  { font-size: 14px; opacity: 0.85; }
  .section { margin-bottom: 28px; }
  .section h2 {
    font-size: 16px;
    color: #059669;
    border-bottom: 2px solid #d1fae5;
    padding-bottom: 8px;
    margin-bottom: 16px;
    text-transform: uppercase;
  }
  .exercise-card {
    background: #f0fdf4;
    border: 1px solid #d1fae5;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
  }
  .exercise-card h3 { color: #065f46; font-size: 14px; margin-bottom: 6px; }
  .exercise-card p  { color: #555; font-size: 13px; line-height: 1.6; }
  .stats-row {
    display: flex;
    gap: 12px;
    margin-top: 8px;
  }
  .stat-pill {
    background: #d1fae5;
    color: #065f46;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
  }
  .week-header {
    background: #7C3AED;
    color: white;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 10px;
  }
  .footer {
    margin-top: 40px;
    text-align: center;
    color: #aaa;
    font-size: 11px;
    border-top: 1px solid #eee;
    padding-top: 16px;
  }
</style>
</head>
<body>

  <div class="header">
    <h1>💪 Personalized Fitness Plan</h1>
    <p>Prepared by Rakhi AI Health Coach &bull; {{ $date }}</p>
  </div>

  <!-- User Summary -->
  <div class="section">
    <h2>Your Profile</h2>
    <div style="display:flex; gap:20px; flex-wrap:wrap;">
      @foreach([
        ['Name', $user->first_name . ' ' . $user->last_name],
        ['Age', $user->age() . ' years'],
        ['Weight', $user->weight . ' kg'],
        ['Activity', ucfirst($user->activity_level ?? 'moderate')],
      ] as [$label, $value])
      <div style="background:#f8f4ff; padding:12px 16px;
                  border-radius:8px; min-width:100px;">
        <div style="font-size:11px; color:#888;">{{ $label }}</div>
        <div style="font-size:14px; font-weight:bold; color:#333;">
          {{ $value }}
        </div>
      </div>
      @endforeach
    </div>
  </div>

  <!-- Weekly Schedule -->
  @if(isset($plan['weeks']))
  @foreach($plan['weeks'] as $weekIndex => $week)
  <div class="section">
    <div class="week-header">
      Week {{ $weekIndex + 1 }} — {{ $week['focus'] ?? '' }}
    </div>
    @foreach($week['days'] as $day)
    <div class="exercise-card">
      <h3>{{ $day['day'] }}</h3>
      <p>{{ $day['description'] }}</p>
      @if(isset($day['exercises']))
      <div class="stats-row">
        @foreach($day['exercises'] as $ex)
          <span class="stat-pill">{{ $ex }}</span>
        @endforeach
      </div>
      @endif
      @if(isset($day['duration']))
        <p style="margin-top:6px; font-size:12px; color:#059669;">
          ⏱ {{ $day['duration'] }} minutes
        </p>
      @endif
    </div>
    @endforeach
  </div>
  @endforeach
  @endif

  <!-- Tips -->
  @if(isset($plan['tips']))
  <div class="section">
    <h2>Fitness Tips</h2>
    @foreach($plan['tips'] as $tip)
    <div style="background:#f0fdf4; border-left:4px solid #059669;
                padding:12px 16px; border-radius:8px; margin-bottom:8px;">
      <p style="font-size:13px; color:#555;">💡 {{ $tip }}</p>
    </div>
    @endforeach
  </div>
  @endif

  <div class="footer">
    <p>Prepared by Rakhi AI — For wellness guidance only.
       Consult a physician before starting any exercise program.</p>
    <p style="margin-top:6px;">🌸 Rakhi AI &bull; {{ $date }}</p>
  </div>

</body>
</html>
