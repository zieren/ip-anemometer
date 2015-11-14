import random

def new_value(old_value, max_delta, min_value, max_value):
  new_value = old_value + random.random() * 2 * max_delta - max_delta
  new_value = min(new_value, max_value)
  new_value = max(new_value, min_value)
  return new_value