import random


def random_value(min_value, max_value):
  return random.random() * (max_value - min_value) + min_value
