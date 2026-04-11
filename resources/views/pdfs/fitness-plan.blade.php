<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', sans-serif;
    background: #f0fdf8;
    color: #1a1a1a;
    font-size: 13px;
    line-height: 1.75;
  }

  /* ── Header ── */
  .header {
    background-color: #047857;
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
    color: #064e3b;
    margin-bottom: 14px;
  }

  /* ── Profile stat cards ── */
  .profile-table { width: 100%; border-collapse: separate; border-spacing: 8px; }
  .profile-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 10px;
    text-align: center;
    border: 1px solid #d1fae5;
  }
  .profile-card .p-icon { font-size: 20px; margin-bottom: 5px; }
  .profile-card .p-value { font-size: 15px; font-weight: bold; color: #047857; }
  .profile-card .p-label { font-size: 11px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }

  /* ── Week banner ── */
  .week-banner {
    background-color: #047857;
    color: #ffffff;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: bold;
    margin-bottom: 12px;
    letter-spacing: 0.2px;
  }
  .week-banner .week-focus {
    font-size: 12px;
    font-weight: normal;
    opacity: 0.85;
    margin-top: 2px;
  }

  /* ── Exercise card ── */
  .exercise-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #d1fae5;
    margin-bottom: 10px;
    overflow: hidden;
  }
  .exercise-card-header {
    background: #ecfdf5;
    padding: 10px 16px;
    border-bottom: 1px solid #d1fae5;
  }
  .day-badge {
    display: inline-block;
    background: #047857;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
  }
  .exercise-card-body { padding: 12px 16px; }
  .day-desc {
    font-size: 13px;
    color: #444;
    line-height: 1.75;
  }
  .pill {
    display: inline-block;
    background: #d1fae5;
    color: #065f46;
    padding: 3px 11px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    margin: 5px 3px 0 0;
    border: 1px solid #a7f3d0;
  }
  .duration-tag {
    display: inline-block;
    background: #f0fdf4;
    color: #047857;
    font-size: 12px;
    font-weight: bold;
    padding: 3px 12px;
    border-radius: 20px;
    border: 1px solid #d1fae5;
    margin-top: 10px;
  }

  /* ── Tip card ── */
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
    border-top: 1px solid #d1fae5;
    margin: 24px 0;
  }

  /* ── Footer ── */
  .footer {
    margin-top: 36px;
    text-align: center;
    color: #aaa;
    font-size: 11px;
    border-top: 1px solid #d1fae5;
    padding-top: 16px;
    line-height: 1.9;
  }
  .footer-brand {
    font-size: 12px;
    font-weight: bold;
    color: #047857;
    margin-top: 6px;
  }
</style>
</head>
<body>

  <!-- ── Header ── -->
  <div class="header">
    <div class="header-top">&#127807; Rakhi AI &nbsp;&bull;&nbsp; Health Coach</div>
    <div class="header-greeting">Let's move, {{ $user->first_name }}! &#128170;</div>
    <div class="header-sub">Your personalized fitness plan is ready. Let's crush those goals!</div>
    <table class="header-meta-table">
      <tr>
        <td><strong>{{ $user->age() }} yrs</strong>Age</td>
        <td><strong>{{ $user->weight }} kg</strong>Weight</td>
        <td><strong>{{ ucfirst($user->activity_level ?? 'Moderate') }}</strong>Activity</td>
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- ── Profile ── -->
    <div class="section">
      <div class="section-title">&#128100; Your Profile</div>
      <table class="profile-table">
        <tr>
          <td class="profile-card">
            <div class="p-icon">&#128100;</div>
            <div class="p-value">{{ $user->first_name }} {{ $user->last_name }}</div>
            <div class="p-label">Name</div>
          </td>
          <td class="profile-card">
            <div class="p-icon">&#127874;</div>
            <div class="p-value">{{ $user->age() }} yrs</div>
            <div class="p-label">Age</div>
          </td>
          <td class="profile-card">
            <div class="p-icon">&#9878;</div>
            <div class="p-value">{{ $user->weight }} kg</div>
            <div class="p-label">Weight</div>
          </td>
          <td class="profile-card">
            <div class="p-icon">&#128293;</div>
            <div class="p-value">{{ ucfirst($user->activity_level ?? 'Moderate') }}</div>
            <div class="p-label">Activity Level</div>
          </td>
        </tr>
      </table>
    </div>

    <hr class="divider">

    <!-- ── Weekly Schedule ── -->
    @if(isset($plan['weeks']))
    @foreach($plan['weeks'] as $weekIndex => $week)
    <div class="section">
      <div class="week-banner">
        &#128197; Week {{ $weekIndex + 1 }}
        @if(!empty($week['focus']))
        <div class="week-focus">Focus: {{ $week['focus'] }}</div>
        @endif
      </div>
      @foreach($week['days'] as $day)
      <div class="exercise-card">
        <div class="exercise-card-header">
          <span class="day-badge">{{ $day['day'] }}</span>
        </div>
        <div class="exercise-card-body">
          @if(!empty($day['description']))
            <div class="day-desc">{{ $day['description'] }}</div>
          @endif
          @if(isset($day['exercises']))
            <div style="margin-top:8px;">
              @foreach($day['exercises'] as $ex)
                <span class="pill">{{ $ex }}</span>
              @endforeach
            </div>
          @endif
          @if(isset($day['duration']))
            <div><span class="duration-tag">&#9201; {{ $day['duration'] }} min</span></div>
          @endif
        </div>
      </div>
      @endforeach
    </div>
    @endforeach
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
        Prepared by Rakhi AI for wellness guidance only.<br>
        Consult a physician before starting any new exercise program.
      </p>
      <div class="footer-brand">&#127807; Rakhi AI &nbsp;&bull;&nbsp; rakhi.ai &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
