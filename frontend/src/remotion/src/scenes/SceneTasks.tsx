import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Sequence } from "remotion";
import { Label } from "../components/Label";
import { TaskCard } from "../components/TaskCard";

export const SceneTasks: React.FC = () => {
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
          <Label>TASKS</Label>
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
          Track. Prioritize. Execute.
        </div>
        <div
          style={{
            fontSize: "18px",
            color: "#6b6b80",
            lineHeight: 1.6,
            opacity: bodyOpacity,
          }}
        >
          Track priorities. Set deadlines. Execute with precision.
        </div>
      </div>

      {/* Right side - Task cards */}
      <div
        style={{
          width: "50%",
          padding: "60px 40px",
          display: "flex",
          flexDirection: "column",
          justifyContent: "center",
          opacity: cardOpacity,
          transform: `translateX(${cardTranslateX}px)`,
        }}
      >
        <Sequence from={10}>
          <TaskCard
            title="Complete project proposal"
            priority="HIGH"
            tags={["Work", "Urgent"]}
            dueDate="Today"
            delay={0}
          />
        </Sequence>
        <Sequence from={25}>
          <TaskCard
            title="Review design mockups"
            priority="MED"
            tags={["Design"]}
            dueDate="Tomorrow"
            delay={0}
          />
        </Sequence>
        <Sequence from={40}>
          <TaskCard
            title="Update documentation"
            priority="LOW"
            tags={["Docs"]}
            dueDate="This week"
            delay={0}
          />
        </Sequence>
        <Sequence from={55}>
          <TaskCard
            title="Team retrospective"
            priority="MED"
            tags={["Team", "Meeting"]}
            dueDate="Friday"
            delay={0}
          />
        </Sequence>
      </div>
    </AbsoluteFill>
  );
};
