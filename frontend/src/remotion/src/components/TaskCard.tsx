import React from "react";
import { useCurrentFrame, interpolate } from "remotion";

interface TaskCardProps {
  title: string;
  priority: "HIGH" | "MED" | "LOW";
  tags: string[];
  dueDate: string;
  delay: number;
}

const priorityColors = {
  HIGH: "#f87171",
  MED: "#7c6df0",
  LOW: "#3d3d50",
};

export const TaskCard: React.FC<TaskCardProps> = ({
  title,
  priority,
  tags,
  dueDate,
  delay,
}) => {
  const frame = useCurrentFrame();

  const opacity = interpolate(
    frame,
    [delay, delay + 20],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const translateY = interpolate(
    frame,
    [delay, delay + 20],
    [40, 0],
    { extrapolateRight: "clamp" }
  );

  return (
    <div
      style={{
        backgroundColor: "#141417",
        border: "1px solid #1f1f26",
        borderRadius: "12px",
        padding: "16px",
        marginBottom: "12px",
        borderLeft: `4px solid ${priorityColors[priority]}`,
        opacity,
        transform: `translateY(${translateY}px)`,
      }}
    >
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "8px" }}>
        <div
          style={{
            fontSize: "16px",
            fontWeight: 600,
            color: "#f0f0f0",
          }}
        >
          {title}
        </div>
        <span
          style={{
            backgroundColor: priorityColors[priority],
            color: "#fff",
            fontSize: "10px",
            fontWeight: 700,
            padding: "4px 8px",
            borderRadius: "4px",
            textTransform: "uppercase",
            letterSpacing: "0.05em",
          }}
        >
          {priority}
        </span>
      </div>
      <div style={{ display: "flex", gap: "6px", marginBottom: "12px" }}>
        {tags.map((tag) => (
          <span
            key={tag}
            style={{
              backgroundColor: "#1f1f26",
              color: "#a89ff5",
              fontSize: "11px",
              padding: "3px 8px",
              borderRadius: "4px",
            }}
          >
            {tag}
          </span>
        ))}
      </div>
      <div
        style={{
          fontSize: "12px",
          color: "#6b6b80",
        }}
      >
        Due {dueDate}
      </div>
    </div>
  );
};
