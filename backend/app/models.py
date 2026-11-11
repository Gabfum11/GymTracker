from app.database import Base
from sqlalchemy.orm import Mapped, mapped_column
from sqlalchemy import String, DateTime, false, func, ForeignKey, Numeric
from datetime import datetime
from decimal import Decimal

class Exercise(Base):
    __tablename__="exercises"
    id: Mapped[int] = mapped_column(primary_key=True)
    name:Mapped[str] = mapped_column(String(100))
    is_weighted_bodyweight:Mapped[bool] = mapped_column(server_default=false())
    created_at:Mapped[datetime]=mapped_column(DateTime(timezone=True), server_default=func.now())

class Workout(Base):
    __tablename__="workouts"
    id: Mapped[int] = mapped_column(primary_key=True)
    started_at: Mapped[datetime]=mapped_column(DateTime(timezone=True), server_default=func.now())
    ended_at: Mapped[datetime | None]=mapped_column(DateTime(timezone=True))

class WorkoutSet(Base):
    __tablename__="sets"
    id: Mapped[int] = mapped_column(primary_key=True)
    workout_id:Mapped[int] =mapped_column(ForeignKey("workouts.id", ondelete="CASCADE"))
    exercise_id:Mapped[int] = mapped_column(ForeignKey("exercises.id"))
    position:Mapped[int]
    weight_kg:Mapped[Decimal]=mapped_column(Numeric(5,2)) #massimo 5 cifre di cui 2 dopo la virgola
    reps:Mapped[int]
    created_at:Mapped[datetime]=mapped_column(DateTime(timezone=True), server_default=func.now())
