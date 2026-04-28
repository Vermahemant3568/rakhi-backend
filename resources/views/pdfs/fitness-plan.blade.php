<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; background: #f0fdf8; color: #1a1a1a; font-size: 13px; line-height: 1.75; }

  .header { background-color: #047857; color: #ffffff; padding: 32px 32px 28px; }
  .header-top { font-size: 11px; letter-spacing: 1.2px; text-transform: uppercase; opacity: 0.75; margin-bottom: 10px; }
  .header-greeting { font-size: 24px; font-weight: bold; margin-bottom: 4px; }
  .header-sub { font-size: 13px; opacity: 0.85; margin-bottom: 18px; }
  .header-meta-table { width: 100%; border-collapse: collapse; }
  .header-meta-table td { font-size: 11px; color: rgba(255,255,255,0.75); padding: 0 16px 0 0; white-space: nowrap; }
  .header-meta-table td strong { display: block; font-size: 13px; color: #ffffff; font-weight: bold; }

  .page { padding: 26px 28px 40px; }
  .section { margin-bottom: 28px; }
  .section-title { font-size: 15px; font-weight: bold; color: #064e3b; margin-bottom: 14px; }

  .overview-table { width: 100%; border-collapse: separate; border-spacing: 8px; }
  .overview-card { background: #ffffff; border-radius: 12px; padding: 14px 10px; text-align: center; border: 1px solid #d1fae5; }
  .overview-card .o-value { font-size: 13px; font-weight: bold; color: #047857; }
  .overview-card .o-label { font-size: 10px; color: #888; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }

  .week-banner { background-color: #047857; color: #ffffff; padding: 12px 18px; border-radius: 10px; font-size: 14px; font-weight: bold; margin-bottom: 12px; }
  .week-banner .week-focus { font-size: 12px; font-weight: normal; opacity: 0.85; margin-top: 2px; }

  .exercise-card { background: #ffffff; border-radius: 12px; border: 1px solid #d1fae5; margin-bottom: 10px; overflow: hidden; }
  .exercise-card-header { background: #ecfdf5; padding: 10px 16px; border-bottom: 1px solid #d1fae5; }
  .day-badge { display: inline-block; background: #047857; color: #fff; font-size: 10px; font-weight: bold; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.6px; }
  .activity-badge { display: inline-block; background: #d1fae5; color: #065f46; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; margin-left: 6px; border: 1px solid #a7f3d0; }
  .intensity-badge { display: inline-block; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 20px; margin-left: 4px; }
  .intensity-low { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
  .intensity-moderate { background: #fefce8; color: #854d0e; border: 1px solid #fde68a; }
  .intensity-high { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  .exercise-card-body { padding: 12px 16px; }
  .day-desc { font-size: 13px; color: #444; line-height: 1.75; }
  .pill { display: inline-block; background: #d1fae5; color: #065f46; padding: 3px 11px; border-radius: 20px; font-size: 11px; font-weight: bold; margin: 5px 3px 0 0; border: 1px solid #a7f3d0; }
  .duration-tag { display: inline-block; background: #f0fdf4; color: #047857; font-size: 12px; font-weight: bold; padding: 3px 12px; border-radius: 20px; border: 1px solid #d1fae5; margin-top: 8px; }
  .safety-note { margin-top: 8px; font-size: 11px; color: #b45309; font-style: italic; }

  .tip-card { background: #ffffff; border-radius: 12px; border: 1px solid #fde68a; padding: 14px 16px; margin-bottom: 10px; }
  .tip-label { font-size: 11px; font-weight: bold; color: #b45309; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 5px; }
  .tip-text { font-size: 13px; color: #4b4b4b; line-height: 1.75; }

  .precaution-card { background: #eff6ff; border-radius: 10px; border: 1px solid #bfdbfe; padding: 10px 14px; margin-bottom: 8px; }
  .precaution-text { font-size: 12px; color: #1e40af; line-height: 1.6; }

  .stop-card { background: #fff1f2; border-radius: 10px; border: 1px solid #fecdd3; padding: 10px 14px; margin-bottom: 8px; }
  .stop-text { font-size: 12px; color: #9f1239; line-height: 1.6; }

  .divider { border: none; border-top: 1px solid #d1fae5; margin: 22px 0; }

  .footer { margin-top: 36px; text-align: center; color: #aaa; font-size: 11px; border-top: 1px solid #d1fae5; padding-top: 16px; line-height: 1.9; }
  .footer-brand { font-size: 12px; font-weight: bold; color: #047857; margin-top: 6px; }
</style>
</head>
<body>

  <div class="header">
    <div class="header-top">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; Fitness Plan</div>
    <div class="header-greeting">Let's move, {{ $user->first_name }}! &#128170;</div>
    <div class="header-sub">Your personalized fitness plan — safe, progressive, and built for you.</div>
    <table class="header-meta-table">
      <tr>
        <td><strong>{{ $user->getAge() }} yrs</strong>Age</td>
        <td><strong>{{ $user->weight }} kg</strong>Weight</td>
        <td><strong>{{ ucfirst($user->activity_level ?? 'Moderate') }}</strong>Activity</td>
        <td><strong>{{ $date }}</strong>Generated</td>
      </tr>
    </table>
  </div>

  <div class="page">

    <!-- Plan Overview -->
    @if(isset($plan['overview']))
    <div class="section">
      <div class="section-title">&#128200; Plan Overview</div>
      <table class="overview-table">
        <tr>
          @if(!empty($plan['overview']['difficulty']))
          <td class="overview-card">
            <div class="o-value">{{ ucfirst($plan['overview']['difficulty']) }}</div>
            <div class="o-label">Difficulty</div>
          </td>
          @endif
          @if(!empty($plan['overview']['primary_activity']))
          <td class="overview-card">
            <div class="o-value">{{ $plan['overview']['primary_activity'] }}</div>
            <div class="o-label">Primary Activity</div>
          </td>
          @endif
          @if(!empty($plan['overview']['weekly_commitment']))
          <td class="overview-card">
            <div class="o-value">{{ $plan['overview']['weekly_commitment'] }}</div>
            <div class="o-label">Weekly Commitment</div>
          </td>
          @endif
        </tr>
      </table>
      @if(!empty($plan['overview']['goal_of_plan']))
        <div style="margin-top:12px; background:#ecfdf5; border-radius:10px; padding:12px 16px; border:1px solid #d1fae5; font-size:13px; color:#065f46;">
          &#127919; {{ $plan['overview']['goal_of_plan'] }}
        </div>
      @endif
    </div>
    <hr class="divider">
    @endif

    <!-- Weekly Schedule -->
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
          @if(!empty($day['activity_type']) && $day['activity_type'] !== 'rest')
            <span class="activity-badge">{{ ucfirst(str_replace('_', ' ', $day['activity_type'])) }}</span>
          @endif
          @if(!empty($day['intensity']))
            <span class="intensity-badge intensity-{{ $day['intensity'] }}">{{ ucfirst($day['intensity']) }}</span>
          @endif
        </div>
        <div class="exercise-card-body">
          @if(!empty($day['description']))
            <div class="day-desc">{{ $day['description'] }}</div>
          @endif
          @if(!empty($day['exercises']))
            <div style="margin-top:8px;">
              @foreach($day['exercises'] as $ex)
                <span class="pill">{{ $ex }}</span>
              @endforeach
            </div>
          @endif
          @if(isset($day['duration']) && $day['duration'] > 0)
            <div><span class="duration-tag">&#9201; {{ $day['duration'] }} min</span></div>
          @endif
          @if(!empty($day['safety_note']))
            <div class="safety-note">&#9888; {{ $day['safety_note'] }}</div>
          @endif
        </div>
      </div>
      @endforeach
    </div>
    @endforeach
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

    <!-- Safety Precautions -->
    @if(!empty($plan['safety_precautions']))
    <div class="section">
      <div class="section-title">&#9989; Safety Precautions</div>
      @foreach($plan['safety_precautions'] as $p)
      <div class="precaution-card">
        <div class="precaution-text">&#8226; {{ $p }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <!-- When to Stop -->
    @if(!empty($plan['when_to_stop']))
    <div class="section">
      <div class="section-title">&#128721; Stop Exercising If You Experience</div>
      @foreach($plan['when_to_stop'] as $s)
      <div class="stop-card">
        <div class="stop-text">&#9888; {{ $s }}</div>
      </div>
      @endforeach
    </div>
    @endif

    <div class="footer">
      <p>
        Prepared by Rakhi Health Coach for wellness guidance only.<br>
        Consult a physician before starting any new exercise program.
      </p>
      <div class="footer-brand">&#127807; Rakhi Health Coach &nbsp;&bull;&nbsp; {{ $date }}</div>
    </div>

  </div>
</body>
</html>
