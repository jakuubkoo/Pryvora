import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Sequence } from "remotion";
import { Label } from "../components/Label";
import { StatBox } from "../components/StatBox";

export const SceneDashboard: React.FC = () => {
  const frame = useCurrentFrame();

  const labelOpacity = interpolate(
    frame,
    [0, 20],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineOpacity = interpolate(
    frame,
    [10, 40],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineTranslateY = interpolate(
    frame,
    [10, 40],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  const bodyOpacity = interpolate(
    frame,
    [20, 50],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const cardOpacity = interpolate(
    frame,
    [20, 70],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const cardTranslateX = interpolate(
    frame,
    [20, 70],
    [80, 0],
    { extrapolateRight: "clamp" }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "#0d0d0f",
        display: "flex",
      }}
    >
      {/* Left side - Text */}
      <div
        style={{
          width: "50%",
          padding: "80px",
          display: "flex",
          flexDirection: "column",
          justifyContent: "center",
        }}
      >
        <div style={{ opacity: labelOpacity }}>
          <Label>DASHBOARD</Label>
        </div>
        <div
          style={{
            fontSize: "56px",
            fontWeight: 700,
            color: "#f0f0f0",
            lineHeight: 1.1,
            opacity: headlineOpacity,
            transform: `translateY(${headlineTranslateY}px)`,
            marginBottom: "16px",
          }}
        >
          Everything at a glance.
        </div>
        <div
          style={{
            fontSize: "18px",
            color: "#6b6b80",
            lineHeight: 1.6,
            opacity: bodyOpacity,
          }}
        >
          See everything that matters. Overdue tasks, today's agenda, your latest notes — all in one place.
        </div>
      </div>

      {/* Right side - Dashboard mock */}
      <div
        style={{
          width: "50%",
          padding: "60px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          opacity: cardOpacity,
          transform: `translateX(${cardTranslateX}px)`,
        }}
      >
        <div
          style={{
            backgroundColor: "#141417",
            border: "1px solid #1f1f26",
            borderRadius: "16px",
            padding: "32px",
            width: "100%",
          }}
        >
          <div
            style={{
              fontSize: "20px",
              fontWeight: 600,
              color: "#f0f0f0",
              marginBottom: "24px",
            }}
          >
            Good morning, Alex
          </div>

          {/* Stats */}
          <div style={{ display: "flex", gap: "16px", marginBottom: "24px" }}>
            <StatBox label="Today's Tasks" value={12} delay={0} />
            <StatBox label="Upcoming" value={8} delay={10} />
            <StatBox label="Active Notes" value={24} delay={20} />
          </div>

          {/* Task rows */}
          <div style={{ display: "flex", flexDirection: "column", gap: "12px" }}>
            <div
              style={{
                backgroundColor: "#1f1f26",
                borderRadius: "8px",
                padding: "12px 16px",
                display: "flex",
                alignItems: "center",
                gap: "12px",
                borderLeft: "3px solid #f87171",
              }}
            >
              <div style={{ width: "16px", height: "16px", borderRadius: "4px", border: "2px solid #6b6b80" }} />
              <div style={{ flex: 1, fontSize: "14px", color: "#f0f0f0" }}>Review Q4 budget report</div>
              <div style={{ fontSize: "12px", color: "#f87171" }}>Overdue</div>
            </div>
            <div
              style={{
                backgroundColor: "#1f1f26",
                borderRadius: "8px",
                padding: "12px 16px",
                display: "flex",
                alignItems: "center",
                gap: "12px",
                borderLeft: "3px solid #7c6df0",
              }}
            >
              <div style={{ width: "16px", height: "16px", borderRadius: "4px", border: "2px solid #6b6b80" }} />
              <div style={{ flex: 1, fontSize: "14px", color: "#f0f0f0" }}>Team sync meeting</div>
              <div style={{ fontSize: "12px", color: "#6b6b80" }}>Today</div>
            </div>
          </div>
        </div>
      </div>
    </AbsoluteFill>
  );
};
