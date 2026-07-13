import React from "react";

interface LabelProps {
  children: React.ReactNode;
  color?: string;
}

export const Label: React.FC<LabelProps> = ({ children, color = "#6b6b80" }) => {
  return (
    <div
      style={{
        fontSize: "12px",
        fontWeight: 500,
        color,
        letterSpacing: "0.25em",
        textTransform: "uppercase",
        marginBottom: "12px",
      }}
    >
      {children}
    </div>
  );
};
